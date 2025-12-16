<?php

declare(strict_types = 1);

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Http\Utilities\ServiceResponse;
use App\Models\License;
use App\Models\User;
use http\Exception\InvalidArgumentException;
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
                    throw new InvalidArgumentException('Usuário já cadastrado.');
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
    public function index(array $data): ServiceResponse
    {
        try {



            return ServiceResponse::success($data, 'Registros encontrados com sucesso.');
        } catch (UnauthorizedException $e) {

            return ServiceResponse::error($e, $e->getCode());
        } catch (\InvalidArgumentException $e) {

            return ServiceResponse::error($e, 400, $e->getMessage());
        } catch (\Exception $e) {

            return ServiceResponse::error($e);
        }
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
