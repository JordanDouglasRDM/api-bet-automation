<?php

declare(strict_types = 1);

namespace Tests\Feature\V1;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Override;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    protected string $baseUrl;

    protected User $user;

    protected string $password = '#Password@123';

    protected string $completeUrl;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->baseUrl     = '/api/v1/auth';
        $this->user        = User::first();
        $this->completeUrl = config('app.url') . $this->baseUrl;
    }

    public function testLoginValidoRetornaTokensEStatus200(): void
    {
        $response = $this->postJson("{$this->baseUrl}/login", [
            'email'    => $this->user->email,
            'password' => $this->password,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'access_token',
                'device_token',
                'token_type',
                'expires_at',
            ],
        ]);
        $this->assertEquals('bearer', $response['data']['token_type']);
        $this->assertTrue($response['data']['expires_at'] > 0);
    }

    public function testLoginComSenhaIncorretaRetorna401(): void
    {
        $response = $this->postJson("{$this->baseUrl}/login", [
            'email'    => $this->user->email,
            'password' => 'senha_errada',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
        $response->assertJsonMissingPath('data.access_token');
        $response->assertJsonMissingPath('data.refresh_token');
        $this->assertEquals('Usuário ou senha inválidos.', $response['message'] ?? '');
    }

    /**
     * @throws ConnectionException
     */
    public function testRefreshTokenValidoComHttpClient(): void
    {
        $loginResponse = Http::asJson()->post("{$this->completeUrl}/login", [
            'email'    => $this->user->email,
            'password' => $this->password,
        ]);

        $this->assertEquals(200, $loginResponse->status());
        $cookie = $loginResponse->cookies()->getCookieByName('refresh_token');

        $this->assertNotNull($cookie, 'Cookie de refresh_token não encontrado.');

        $refreshResponse = Http::withCookies([
            'refresh_token' => $cookie->getValue(),
        ], 'localhost')
            ->post("{$this->completeUrl}/refresh-token");

        $this->assertEquals(200, $refreshResponse->status());

        $this->assertNotEmpty($refreshResponse->json('data.access_token'));
        $this->assertNotEmpty($refreshResponse->json('data.token_type'));
        $this->assertNotEmpty($refreshResponse->json('data.expires_at'));
    }

    /**
     * @throws ConnectionException
     */
    public function testLogoutRevogaRefreshTokenEImpedeReusoDeRefreshTokenRevogado(): void
    {
        // login
        $loginResponse = Http::asJson()->post("{$this->completeUrl}/login", [
            'email'    => $this->user->email,
            'password' => $this->password,
        ]);

        $loginResponseJson = $loginResponse->json();
        $this->assertEquals(200, $loginResponse->status(), 'Houve uma falha ao realizar o login.');
        $cookie = $loginResponse->cookies()->getCookieByName('refresh_token');

        $this->assertNotNull($cookie, 'Cookie de refresh_token não encontrado.');
        $this->assertNotEmpty($loginResponse->json('data.access_token'));
        $this->assertNotEmpty($loginResponse->json('data.device_token'));
        $accessToken = $loginResponseJson['data']['access_token'];
        $deviceToken = $loginResponseJson['data']['device_token'];

        $logoutResponse = Http::asJson()
            ->withToken($accessToken)
            ->post("{$this->completeUrl}/logout", ['device_token' => $deviceToken]);

        $this->assertEquals(200, $logoutResponse->status(), 'Houve uma falha ao realizar o logout.');
        $this->assertNotEmpty($loginResponse->json('message'));

        // tentar utilizar o refresh token
        $refreshResponse = Http::withCookies([
            'refresh_token' => $cookie->getValue(),
        ], 'localhost')
            ->post("{$this->completeUrl}/refresh-token");

        $this->assertEquals(401, $refreshResponse->status());
        $this->assertNull($refreshResponse->json('data'));
    }

    /**
     * @throws ConnectionException
     */
    public function testRefreshTokenInvalidoRetorna401(): void
    {
        $refreshResponse = Http::withCookies([
            'refresh_token' => 'token_invalido_teste',
        ], 'localhost')
            ->post("{$this->completeUrl}/refresh-token");

        $this->assertEquals(401, $refreshResponse->status());
        $this->assertNotEmpty($refreshResponse->json('message'));
        $this->assertNull($refreshResponse->json('data'));
    }

    public function testAcessoSemTokenRetorna401(): void
    {
        $response = $this->getJson("{$this->baseUrl}/logged");

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'status',
            'message',
            'data',
        ]);
        $this->assertEquals('Usuário não autenticado.', $response['message'] ?? '');
    }

    /**
     * Helper: efetua login retornando access_token, device_token e user-agent usado.
     * @return array<string,mixed>
     */
    private function loginComUserAgent(string $userAgent = 'Chrome'): array
    {
        $resp = $this->withHeaders(['User-Agent' => $userAgent])
            ->postJson("{$this->baseUrl}/login", [
                'email'    => $this->user->email,
                'password' => $this->password,
            ]);

        $resp->assertStatus(200);
        $resp->assertJsonStructure([
            'data' => ['access_token', 'device_token', 'token_type', 'expires_at'],
        ]);

        return [
            'access_token' => $resp->json('data.access_token'),
            'device_token' => $resp->json('data.device_token'),
            'user_agent'   => $userAgent,
        ];
    }

    private function getCountTokenActiveByUser(User $user): int
    {
        return RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->count();
    }

    private function getCountTokenInactiveByUser(User $user): int
    {
        return RefreshToken::where('user_id', $user->id)
            ->whereNotNull('revoked_at')
            ->count();
    }

    public function testLogoutRevogaTodosOsDispositivosQuandoFlagGlobalTrue(): void
    {
        $user = $this->user;

        $countTokensBefore = $this->getCountTokenActiveByUser($user);

        RefreshToken::factory()->create([
            'user_id'    => $user->id,
            'revoked_at' => null,
            'user_agent' => 'Chrome',
        ]);
        RefreshToken::factory()->create([
            'user_id'    => $user->id,
            'revoked_at' => null,
            'user_agent' => 'Safari',
        ]);

        // cria um token de outro usuário (controle: não deve ser afetado)
        $outroUser      = User::factory()->create();
        $tokenOutroUser = RefreshToken::factory()->create([
            'user_id'    => $outroUser->id,
            'revoked_at' => null,
            'user_agent' => 'Chrome',
        ]);

        // login cria mais 1 token ativo + provê access_token para autenticar /logout
        $login            = $this->loginComUserAgent('Firefox');
        $countTokensAfter = $this->getCountTokenActiveByUser($user);

        $countTotal = $countTokensAfter - $countTokensBefore;

        $this->assertEquals(3, $countTotal, 'Devem existir 3 tokens novos ativos do usuário antes do logout global');

        // logout global
        $response = $this->withToken($login['access_token'])
            ->withHeaders(['User-Agent' => 'Firefox'])
            ->postJson("{$this->baseUrl}/logout", [
                'revoke_all_devices' => true,
                'device_token'       => $login['device_token'], // ok enviar junto; a service primeiro revoga todos
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logout realizado com sucesso.']);

        // todos os tokens do usuário atual devem estar revogados
        $count = $this->getCountTokenActiveByUser($user);
        $this->assertEquals(0, $count, 'Após logout global NÃO pode sobrar token ativo do usuário');

        $exists = RefreshToken::where('user_id', $user->id)->whereNotNull('revoked_at')->exists();
        $this->assertTrue($exists, 'Pelo menos um token revogado precisa existir');

        // o token de outro usuário permanece ativo
        /** @var RefreshToken $tokenOutroUser */
        $this->assertNull($tokenOutroUser->fresh()->revoked_at, 'Logout global NÃO deve afetar tokens de outro usuário');

        // garantias extras de estrutura
        $response->assertJsonStructure(['message']);
    }

    public function testLogoutRevogaSomenteDispositivoAtualQuandoDeviceTokenInformado(): void
    {
        $user           = $this->user;
        $inactiveBefore = $this->getCountTokenInactiveByUser($user);

        $countTokensBefore = $this->getCountTokenActiveByUser($user);
        // cria tokens ativos de OUTROS dispositivos/UA
        $uaNaoDeveSerRevogado = 'Safari';
        $tOutros1             = RefreshToken::factory()->create([
            'user_id'    => $user->id,
            'user_agent' => $uaNaoDeveSerRevogado,
            'revoked_at' => null,
        ]);
        $tOutros2 = RefreshToken::factory()->create([
            'user_id'    => $user->id,
            'user_agent' => $uaNaoDeveSerRevogado,
            'revoked_at' => null,
        ]);

        // login do dispositivo atual (gera device_token deste "dispositivo")
        $loginResponse = $this->loginComUserAgent('Chrome');

        $countTokensAfter = $this->getCountTokenActiveByUser($user);
        $countTotal       = $countTokensAfter - $countTokensBefore;
        $this->assertEquals(3, $countTotal, 'Devem existir 3 tokens novos ativos do usuário antes do logout global');

        // logout apenas do dispositivo atual (envia device_token)
        $response = $this->withToken($loginResponse['access_token'])
            ->withHeaders(['User-Agent' => 'Safari']) // mesmo se o UA aqui casar com outros tokens, a service irá PRIORIZAR device_token
            ->postJson("{$this->baseUrl}/logout", [
                'revoke_all_devices' => false,
                'device_token'       => $loginResponse['device_token'],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logout realizado com sucesso.']);

        // ASSERTS:
        // 1) Não foi global: ainda existem tokens ativos do usuário
        $activeAfter = $this->getCountTokenActiveByUser($user);
        $this->assertGreaterThan(0, $activeAfter, 'Não deve ser logout global quando device_token é enviado');

        // 2) Revogou exatamente 1 token (o do dispositivo atual do login)
        $inactiveAfter  = $this->getCountTokenInactiveByUser($user);
        $inactiveResult = $inactiveAfter - $inactiveBefore;
        $this->assertEquals(1, $inactiveResult, 'Deve ter exatamente 1 token revogado (o dispositivo atual)');

        // 3) Garante que NÃO foi por user-agent: os tokens Safari continuam ativos
        /** @var RefreshToken $tOutros1 */
        $this->assertNull($tOutros1->fresh()->revoked_at, 'Token Safari 1 não pode ter sido revogado (logout por device, não por user-agent)');
        /** @var RefreshToken $tOutros2 */
        $this->assertNull($tOutros2->fresh()->revoked_at, 'Token Safari 2 não pode ter sido revogado (logout por device, não por user-agent)');

        // 4) Estrutura de resposta
        $response->assertJsonStructure(['message']);
    }

    public function testLogoutRevogaTodosComMesmoUserAgentQuandoNaoHaDeviceToken(): void
    {
        $user = $this->user;

        // cria tokens Chrome (alvos) e um Safari (controle)
        $chrome1 = RefreshToken::factory()->create([
            'user_id'    => $user->id,
            'user_agent' => 'Chrome',
            'revoked_at' => null,
        ]);
        $chrome2 = RefreshToken::factory()->create([
            'user_id'    => $user->id,
            'user_agent' => 'Chrome',
            'revoked_at' => null,
        ]);
        $safari = RefreshToken::factory()->create([
            'user_id'    => $user->id,
            'user_agent' => 'Safari',
            'revoked_at' => null,
        ]);

        // login só para obter access_token (não usaremos device_token no logout)
        $login = $this->loginComUserAgent('Edge');

        // logout por user-agent: sem device_token → cai no branch do userAgent do request
        $response = $this->withToken($login['access_token'])
            ->withHeaders(['User-Agent' => 'Chrome'])
            ->postJson("{$this->baseUrl}/logout", [
                'device_token'       => 'device_token_invalido',
                'revoke_all_devices' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logout realizado com sucesso.']);

        // Chrome devem estar revogados
        /** @var RefreshToken $chrome1 */
        $this->assertNotNull($chrome1->fresh()->revoked_at, 'Token Chrome 1 deve ser revogado');
        /** @var RefreshToken $chrome2 */
        $this->assertNotNull($chrome2->fresh()->revoked_at, 'Token Chrome 2 deve ser revogado');

        /** @var RefreshToken $safari */
        // Safari permanece ativo
        $this->assertNull($safari->fresh()->revoked_at, 'Token Safari não deve ser revogado (logout por user-agent Chrome)');

        // Não foi global: há tokens ativos restantes
        $this->assertGreaterThan(0, $this->getCountTokenActiveByUser($user), 'Não pode ser logout global nesse cenário');

        // Estrutura
        $response->assertJsonStructure(['message']);
    }

    /**
     * 4) Recupera usuário autenticado (/me) após login.
     */
    public function testRecuperaUsuarioAutenticadoComAccessTokenValido(): void
    {
        $login = $this->loginComUserAgent('Firefox');

        $loggedResponse = $this->withToken($login['access_token'])
            ->getJson("{$this->baseUrl}/logged");

        $loggedResponse->assertStatus(200);
        $loggedResponse->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'created_at', 'updated_at'],
        ]);

        $this->assertEquals($this->user->email, $loggedResponse->json('data.email'));
        $this->assertEquals($this->user->id, $loggedResponse->json('data.id'));
        $this->assertIsInt($loggedResponse->json('data.id'));
        $this->assertNotEmpty($loggedResponse->json('data.name'));
        $this->assertNotEmpty($loggedResponse->json('data.created_at'));
    }
}
