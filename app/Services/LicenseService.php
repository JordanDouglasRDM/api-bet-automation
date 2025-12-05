<?php

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Http\Utilities\ServiceResponse;
use App\Models\User;

class LicenseService
{
    public function store(array $data): ServiceResponse
    {
        try {


            return ServiceResponse::success($data, 'Usuários e licenças cadastrados com sucesso.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401, 'Licença expirada ou inexistente.');
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }
    public function check(array $data): ServiceResponse
    {
        try {
            $user = User::where('login', $data['login'])
                ->where('code', $data['code'])
                ->first();
            if (!$user) {
                throw new UnauthorizedException();
            }
            //usuário diferente de adm, verificar licença
            if (!$user->isAdmin()) {
                $licence = $user->license;

                //se não existe licença, emite erro
                if (!$licence) {
                    throw new UnauthorizedException();
                }

                //primeiro uso da licença (primeiro login)
                if (!$licence->starts_at) {
                    $licence->starts_at = now();
                    $licence->save();
                    return ServiceResponse::success($data, 'Usuário com licença válida.');
                }

                //licença revogada, ou expirada
                if (!$licence->isValid()) {
                    throw new UnauthorizedException();
                }


            }

            return ServiceResponse::success($data, 'Usuário com licença válida.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401, 'Licença expirada ou inexistente.');
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }
}