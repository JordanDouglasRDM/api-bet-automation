<?php

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Http\Utilities\ServiceResponse;
use App\Models\License;
use App\Models\User;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function store(array $data): ServiceResponse
    {
        try {
            $this->handleAuth();
            DB::beginTransaction();

            $data['password'] = Hash::make($data['password']);
            $user = User::create($data);
            License::create([
                'user_id'  => $user->id,
                'status'   => 'active',
                'lifetime' => true,
            ]);

            DB::commit();
            return ServiceResponse::success($user, 'Licença e usuário cadastrados com sucesso.');
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
            $id = $data['id'];
            $user = User::findOrFail($id);
            unset($data['id']);
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }
            $user->update($data);

            return ServiceResponse::success(User::findOrFail($id), 'Usuário atualizado com sucesso.');
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

            return ServiceResponse::success($user, 'Usuário removido com sucesso.');
        } catch (ModelNotFoundException $e) {
            return ServiceResponse::error($e, 404, 'Usuário não encontrado.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 403, $e->getMessage());
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }
    public function index(array $data): ServiceResponse
    {
        try {
            $this->handleAuth();

            $users = User::orderBy($data['order_by'], $data['order_direction'])->get();
            $users = $users->map(function (User $user) {
               return [
                   'id' => $user->id,
                   'level' => $user->level,
                   'code' => $user->code,
                   'login' => $user->login,
                   'created_at' => $user->created_at->format('d/m/Y H:i:s'),
                   'updated_at' => $user->updated_at->format('d/m/Y H:i:s'),
               ];
            });
            $message = count($users) > 0 ? 'Registros encontrados com sucesso.' : 'Nenhum registro encontrado.';
            return ServiceResponse::success($users, $message);
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
        if ($userAuth->level !== 'super') {
            throw new UnauthorizedException('Usuário não possui permissão necessária para esta ação.');
        }
    }
}