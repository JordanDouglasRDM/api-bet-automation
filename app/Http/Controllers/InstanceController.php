<?php

declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Http\Requests\DestroyInstanceRequest;
use App\Http\Requests\IndexInstanceRequest;
use App\Http\Requests\ShowInstanceRequest;
use App\Http\Requests\StoreInstanceRequest;
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

            $result = $result->get()->map(function ($item) {
                return [
                    'id'             => $item->id,
                    'nome'           => $item->nome,
                    'usuarios_count' => $item->instance_users_count,
                    'usuarios'       => $item->instanceUsers->map(function ($u) {
                        return [
                            'id'    => $u->usuario_id,
                            'login' => $u->login,
                            'saldo' => $u->saldo,
                        ];
                    }),
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

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
                'usuarios'       => $i->instanceUsers->map(function ($u) {
                    return [
                        'id'    => $u->usuario_id,
                        'login' => $u->login,
                        'saldo' => $u->saldo,
                    ];
                }),
                'created_at'     => $i->created_at,
                'updated_at'     => $i->updated_at,
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
