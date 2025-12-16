<?php

declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Exceptions\UnauthorizedException;
use App\Http\Requests\CloneInstanceRequest;
use App\Http\Requests\DestroyInstanceRequest;
use App\Http\Requests\IndexInstanceRequest;
use App\Http\Requests\ShowInstanceRequest;
use App\Http\Requests\StoreInstanceRequest;
use App\Http\Requests\UpdateInstanceRequest;
use App\Http\Utilities\ResponseFormatter;
use App\Http\Utilities\ServiceResponse;
use App\Models\Instance;
use App\Models\InstanceUser;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InstanceController extends Controller
{
    public function update(UpdateInstanceRequest $request): JsonResponse
    {
        $data        = $request->validated();
        $instanciaId = $data['id'];
        $authId      = $request->header('x-auth-id') ?? $data['auth_id'];

        // IDs de usuário enviados no payload
        $usuariosPayload = collect($data['usuarios']);

        $usuarioIdsPayload = $usuariosPayload->pluck('id_pk')->filter()->unique();

        //Segurança: impedir vínculo entre usuários de outra conta
        $existsForeign = InstanceUser::whereIn('id', $usuarioIdsPayload)
            ->where('auth_id', '!=', $authId)
            ->exists();

        if ($existsForeign) {
            return ResponseFormatter::format(
                ServiceResponse::error(
                    new UnauthorizedException(),
                    status: 401,
                    message: 'Existe algum usuário que não pertence ao usuário autenticado.'
                )
            );
        }

        DB::beginTransaction();

        try {
            // trava a instância
            $instance = Instance::where('id', $instanciaId)
                ->where('auth_id', $authId)
                ->lockForUpdate()
                ->firstOrFail();

            // atualiza nome
            $instance->update([
                'nome' => $data['nome'],
            ]);

            // monta o dataset para upsert
            $rows = $usuariosPayload->map(function ($u) use ($authId, $instance) {
                return [
                    'id'           => $u['id_pk'] ?? null,
                    'auth_id'      => $authId,
                    'instancia_id' => $instance->id,
                    'usuario_id'   => $u['id'],
                    'login'        => $u['login'],
                    'saldo'        => $u['saldo'],
                ];
            })->all();

            // 🎯 Upsert elegante
            // - Se tiver id_pk -> update
            // - Se não tiver -> create
            InstanceUser::upsert(
                $rows,
                ['id'],
                ['login', 'saldo']
            );

            // IDs reais dos usuário_id enviados (não id_pk)
            $usuarioIds = $usuariosPayload->pluck('id')->unique()->all();

            // Excluir quem não está no payload
            InstanceUser::where('auth_id', $authId)
                ->where('instancia_id', $instanciaId)
                ->whereNotIn('usuario_id', $usuarioIds)
                ->delete();

            DB::commit();

            return ResponseFormatter::format(
                ServiceResponse::success(message: 'Instancia atualizada com sucesso.')
            );
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ResponseFormatter::format(
                ServiceResponse::error($e, status: 404, message: $e->getMessage())
            );
        } catch (Exception | \Throwable $e) {
            DB::rollBack();

            return ResponseFormatter::format(
                ServiceResponse::error($e)
            );
        }
    }

    public function clone(CloneInstanceRequest $request): JsonResponse
    {
        $data        = $request->validated();
        $instanciaId = $data['id'];
        $authId      = $request->header('x-auth-id') ?? $data['auth_id'];

        DB::beginTransaction();

        try {
            $instance = Instance::where('id', $instanciaId)
                ->where('auth_id', $authId)
                ->firstOrFail();

            $usersOfInstance = InstanceUser::where('auth_id', $authId)
                ->where('instancia_id', $instanciaId)
                ->get();

            $dataInstance['nome']    = "{$instance->nome} - Cópia";
            $dataInstance['auth_id'] = $authId;
            $cloned                  = Instance::create($dataInstance);

            $usersToClone = $usersOfInstance->map(function (InstanceUser $user) use ($cloned) {
                return [
                    'auth_id'      => $cloned->auth_id,
                    'instancia_id' => $cloned->id,
                    'usuario_id'   => $user->usuario_id,
                    'login'        => $user->login,
                    'saldo'        => $user->saldo,
                ];
            });
            InstanceUser::insert($usersToClone->toArray());

            DB::commit();

            return ResponseFormatter::format(
                ServiceResponse::success(message: 'Instancia clonada com sucesso.')
            );
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ResponseFormatter::format(
                ServiceResponse::error($e, status: 404, message: $e->getMessage())
            );
        } catch (Exception | \Throwable $e) {
            DB::rollBack();

            return ResponseFormatter::format(
                ServiceResponse::error($e)
            );
        }
    }

    public function store(StoreInstanceRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            DB::beginTransaction();

            $i = Instance::create(['nome' => $data['nome'], 'auth_id' => $data['auth_id']]);

            foreach ($data['usuarios'] as $user) {
                InstanceUser::create([
                    'instancia_id' => $i->id,
                    'auth_id'      => $i->auth_id,
                    'usuario_id'   => $user['id'],
                    'login'        => $user['login'],
                    'saldo'        => $user['saldo'],
                ]);
            }
            DB::commit();

            return ResponseFormatter::format(
                ServiceResponse::success(message: 'Instancia criada com sucesso.')
            );
        } catch (Exception | \Throwable $e) {
            DB::rollBack();

            return ResponseFormatter::format(
                ServiceResponse::error($e)
            );
        }
    }

    public function index(IndexInstanceRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $result = Instance::where('auth_id', $data)
                ->with('instanceUsers')
                ->withCount('instanceUsers');

            $result = $result->get()->map(fn ($item): array => [
                'id'             => $item->id,
                'nome'           => $item->nome,
                'usuarios_count' => $item->instance_users_count,
                'usuarios'       => $item->instanceUsers->map(fn ($u): array => [
                    'id_pk' => $u->id,
                    'id'    => $u->usuario_id,
                    'login' => $u->login,
                    'saldo' => $u->saldo,
                ]),
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);

            $message = $result->count() > 0 ? 'Registros encontrados.' : 'Nenhum registro encontrado.';

            return ResponseFormatter::format(
                ServiceResponse::success($result, message: $message)
            );
        } catch (Exception $e) {
            return ResponseFormatter::format(
                ServiceResponse::error($e)
            );
        }
    }

    public function show(ShowInstanceRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $i = Instance::where('auth_id', $data['auth_id'])
                ->where('id', $id)
                ->with('instanceUsers')
                ->withCount('instanceUsers')
                ->firstOrFail();

            $model = [
                'id'             => $i->id,
                'nome'           => $i->nome,
                'usuarios_count' => $i->instance_users_count,
                'usuarios'       => $i->instanceUsers->map(fn ($u): array => [
                    'id'    => $u->usuario_id,
                    'login' => $u->login,
                    'saldo' => $u->saldo,
                ]),
                'created_at' => $i->created_at,
                'updated_at' => $i->updated_at,
            ];

            return ResponseFormatter::format(
                ServiceResponse::success($model, message: 'Instancia encontrada com sucesso.')
            );
        } catch (ModelNotFoundException $e) {
            return ResponseFormatter::format(
                ServiceResponse::error($e, 404, 'Instância não encontrada.')
            );
        } catch (Exception $e) {
            return ResponseFormatter::format(
                ServiceResponse::error($e)
            );
        }
    }

    public function destroy(DestroyInstanceRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $i = Instance::where('auth_id', $data['auth_id'])
                ->where('id', $id)
                ->firstOrFail();
            $i->delete();

            return ResponseFormatter::format(
                ServiceResponse::success(message: 'Instancia removida com sucesso.')
            );
        } catch (ModelNotFoundException $e) {
            return ResponseFormatter::format(
                ServiceResponse::error($e, 404, 'Instância não encontrada.')
            );
        } catch (Exception $e) {
            return ResponseFormatter::format(
                ServiceResponse::error($e)
            );
        }
    }
}
