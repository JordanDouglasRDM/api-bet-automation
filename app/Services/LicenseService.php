<?php

declare(strict_types = 1);

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Http\Utilities\ServiceResponse;
use App\Models\License;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class LicenseService
{
    public function store(array $data): ServiceResponse
    {
        try {
            DB::beginTransaction();
            $users = [];

            foreach ($data['users'] as $user) {
                $userFind = User::where('login', $user['login'])
                    ->where('code', $user['code'])
                    ->first();

                if ($userFind) {
                    throw new \InvalidArgumentException('Usuário já cadastrado.');
                }

                $userCreated = User::create($user);
                $users[]     = $userCreated;
            }

            foreach ($users as $user) {
                License::create([
                    'user_id'    => $user->id,
                    'status'     => $data['status'],
                    'start_at'   => $data['start_at'],
                    'expires_at' => $data['expires_at'],
                    'lifetime'   => $data['lifetime'],
                ]);
            }
            DB::commit();

            return ServiceResponse::success($data, 'Usuários e licenças cadastrados com sucesso.');
        } catch (UnauthorizedException $e) {
            DB::rollBack();

            return ServiceResponse::error($e, 401, 'Licença expirada ou inexistente.');
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return ServiceResponse::error($e, 400, $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();

            return ServiceResponse::error($e);
        }
    }

    public function index(array $data = null): ServiceResponse
    {
        try {
            $licence = License::with('user')
                ->orderBy('licenses.created_at', 'desc')
                ->whereRelation('user', 'level', 'operator')
                ->get();
            $licence = $licence->map(function (License $item) {
                return [
                    'id' => $item->id,
                    //                    'user_id'           => $item->user_id,
                    'status'            => $item->status,
                    'status_translated' => $item->getStatusTranslated(),
                    'severity_tag'      => $item->getSeverityTag(),
                    'start_at'          => optional($item->start_at)->format('d/m/Y H:i') ?? '-',
                    'expires_at'        => optional($item->expires_at)->format('d/m/Y') ?? '-',
                    'expires_at_iso'    => $item->expires_at ? Carbon::parse($item->expires_at)->toISOString() : '-',
                    'activated_at'      => optional($item->activated_at)->format('d/m/Y H:i') ?? '-',
                    'last_use'          => optional($item->last_use)->format('d/m/Y H:i:s') ?? '-',
                    'last_use_iso'      => $item->last_use ? Carbon::parse($item->last_use)->toISOString() : '-',
                    'lifetime'          => $item->lifetime,
                    'lifetime_text'     => $item->lifetime ? 'Sim' : 'Não',
                    'created_at'        => $item->created_at->format('d/m/Y H:i') ?? '-',
                    'updated_at'        => $item->updated_at->format('d/m/Y H:i') ?? '-',
                    'user'              => $item->user,
                ];
            });

            $message = count($licence) > 0 ? 'Registros encontrados com sucesso.' : 'Nenhum registro encontrado.';

            return ServiceResponse::success($licence, $message);
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, $e->getCode());
        } catch (\InvalidArgumentException $e) {
            return ServiceResponse::error($e, 400, $e->getMessage());
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    public function destroy(int $licenseId): ServiceResponse
    {
        try {
            $license = License::findOrFail($licenseId);
            $license->delete();

            return ServiceResponse::success([], 'Licença e usuário removidos com sucesso.');
        } catch (ModelNotFoundException $e) {
            return ServiceResponse::error($e, 404, 'Recurso não encontrado.');
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    public function destroyBatch(array $data): ServiceResponse
    {
        try {
            License::whereIn('id', $data['ids'])->delete();

            return ServiceResponse::success([], $this->getMessageByLengthLicenses($data['ids']));
        } catch (ModelNotFoundException $e) {
            return ServiceResponse::error($e, 404, 'Recurso não encontrado.');
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    private function getMessageByLengthLicenses(array $data): string
    {
        $count = count($data);

        if ($count === 1) {
            return '1 licença e usuário excluído com sucesso.';
        }

        return "{$count} licenças e usuários excluídos com sucesso.";
    }

    public function update(array $data): ServiceResponse
    {
        try {
            $license = License::findOrFail($data['id']);

            return ServiceResponse::success([], 'Licença atualizada com sucesso.');
        } catch (ModelNotFoundException $e) {
            return ServiceResponse::error($e, 404, 'Licença não encontrada.');
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    /**
     * @throws \Throwable
     */
    private function handleRevokeLicense(License $license): void
    {
        $license->updateOrFail(['status' => 'revoked']);
    }

    private function handleRenewLicense(License $license)
    {
        //contar a diferença de dias de 'start_at' e 'expires_at'
        //'start_at' deve ser o dia atual
        //'expires_at' deve ser a diferenças de dias no futuro'
    }

    private function handleActivateLicense(License $license)
    {
    }

    public function check(User $user): ServiceResponse
    {
        try {
            $data = ['status' => 'sucess'];
            //usuário diferente de adm, verificar licença
            $licence = $user->license;

            //se não existe licença, emite erro
            if (! $licence) {
                throw new UnauthorizedException();
            }

            //primeiro uso da licença (primeiro login)
            if (! $licence->starts_at) {
                $licence->starts_at = now();
                $licence->save();

                return ServiceResponse::success($data, 'Usuário com licença válida.');
            }

            //licença revogada, ou expirada
            if (! $licence->isValid()) {
                throw new UnauthorizedException();
            }

            return ServiceResponse::success($data, 'Usuário com licença válida.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401, 'Licença expirada ou inexistente.');
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }
}
