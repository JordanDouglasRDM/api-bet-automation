<?php

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Http\Utilities\ServiceResponse;
use App\Models\License;
use App\Models\User;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class UserService
{
    public function store(array $data): ServiceResponse
    {
        try {
            $this->handleAuth();

            DB::beginTransaction();
            $user = User::create($data);
            License::create([
                'user_id' => $user->id,
                'status' => 'active',
                'lifetime' => true,
            ]);


            DB::commit();
            return ServiceResponse::success([], 'Licença e usuário removidos com sucesso.');
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return ServiceResponse::error($e, 404, 'Recurso não encontrado.');
        } catch (UnauthorizedException $e) {
            DB::rollBack();
            return ServiceResponse::error($e, 403, $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ServiceResponse::error($e);
        }
    }

    public function update(array $data): ServiceResponse
    {
        try {
            $this->handleAuth();

//            $user = User::update($userId);
//            $user->delete();

            return ServiceResponse::success([], 'Usuário removido com sucesso.');
        } catch (ModelNotFoundException $e) {
            return ServiceResponse::error($e, 404, 'Usuário não encontrado.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 403, $e->getMessage());
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }
    public function destroy(int $userId): ServiceResponse
    {
        try {
            $this->handleAuth();

            $user = User::findOrFail($userId);
            $user->delete();

            return ServiceResponse::success([], 'Usuário removido com sucesso.');
        } catch (ModelNotFoundException $e) {
            return ServiceResponse::error($e, 404, 'Usuário não encontrado.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 403, $e->getMessage());
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    /**
     * @return void
     * @throws UnauthorizedException
     */
    public function handleAuth(): void
    {
        $userAuth = Auth::user();
        if ($userAuth->login !== 'jordan.dmelo') {
            throw new UnauthorizedException('Usuário não possui permissão necessária para esta ação.');
        }
    }
}