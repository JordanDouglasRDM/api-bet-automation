<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\LicenseRevokedEvent;
use App\Exceptions\UnauthorizedException;
use App\Helpers\Helper;
use App\Http\Utilities\ServiceResponse;
use App\Models\License;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
                $price = $user['price'];
                $indication = $user['indication'];
                unset($user['indication']);

                unset($user['price']);
                $userCreated = User::create($user);

                $userCreated['price'] = $price;
                $userCreated['indication'] = $indication;
                $users[] = $userCreated;
            }

            foreach ($users as $user) {
                License::create([
                    'user_id'    => $user->id,
                    'status'     => 'active',
                    'price'      => $user['price'],
                    'indication' => $user['indication'],
                    'start_at'   => $data['lifetime'] ? null : $data['start_at'],
                    'expires_at' => $data['lifetime'] ? null : $data['expires_at'],
                    'lifetime'   => $data['lifetime'],
                ]);
            }
            DB::commit();

            return ServiceResponse::success([], 'Usuários e licenças cadastrados com sucesso.');
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
            Carbon::setLocale('pt_BR');
            $licence = License::with('user')
                ->orderBy('licenses.created_at', 'desc')
                ->whereRelation('user', 'level', 'operator')
                ->get();
            $licence = $licence->map(function (License $item) {
                return [
                    'id'                     => $item->id,
                    'status'                 => $item->status,
                    'price'                  => $item->price,
                    'indication'             => $item->indication ?? '-',
                    'price_formatted'        => Helper::toCurrency($item->price),
                    'status_translated'      => $item->getStatusTranslated(),
                    'severity_tag'           => $item->getSeverityTag(),
                    'start_at'               => optional($item->start_at)->format('d/m/Y') ?? '-',
                    'start_iso'              => $item->start_at ? Carbon::parse($item->start_at)->toISOString() : '-',
                    'start_long_text'        => $this->longDate($item->start_at, false),
                    'expires_at'             => optional($item->expires_at)->format('d/m/Y') ?? '-',
                    'expires_at_iso'         => $item->expires_at ? Carbon::parse($item->expires_at)->toISOString() : '-',
                    'expires_long_text'      => $this->longDate($item->expires_at, false),
                    'days'                   => $item->start_at ? Helper::getDaysBetweenDates(
                        $item->start_at->toString(),
                        $item->expires_at->toString()
                    ) : '-',
                    'days_left'              => Helper::getDaysLeftToExpires($item, false),
                    'days_left_formatted'    => Helper::getDaysLeftToExpires($item, true),
                    'cambistas_ativos_count' => $item->cambistas_ativos_count ?? '-',
                    'last_use'               => optional($item->last_use)->format('d/m/Y H:i:s') ?? '-',
                    'last_use_iso'           => $item->last_use ? Carbon::parse($item->last_use)->toISOString() : '-',
                    'last_use_long_text'     => $this->longDate($item->last_use),
                    'lifetime'               => $item->lifetime,
                    'lifetime_text'          => $item->lifetime ? 'Sim' : 'Não',
                    'created_at'             => $item->created_at->format('d/m/Y H:i') ?? '-',
                    'created_long_text'      => $this->longDate($item->created_at),
                    'updated_at'             => $item->updated_at->format('d/m/Y H:i') ?? '-',
                    'updated_long_text'      => $this->longDate($item->updated_at),
                    'user'                   => $item->user,
                ];
            });

            $message = count($licence) > 0 ? 'Registros encontrados com sucesso.' : 'Nenhum registro encontrado.';

            return ServiceResponse::success($licence, $message);
        } catch (\InvalidArgumentException $e) {
            return ServiceResponse::error($e, 400, $e->getMessage());
        } catch (\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    private function longDate(?CarbonInterface $date, bool $hour = true): string
    {
        $format = $hour ? 'l, d \d\e F \d\e Y (H:i:s)' : 'l, d \d\e F \d\e Y';
        return $date
            ? ucfirst($date->locale('pt_BR')
                ->translatedFormat($format))
            : '-';
    }

    public function expiredLicensesCheck(): bool|int
    {
        return License::where('status', 'active')
            ->whereDate('expires_at', '<', Carbon::today())
            ->update([
                'status' => 'expired',
            ]);
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

    private function getMessageByLengthLicensesToRenew(array $data): string
    {
        $count = count($data);

        if ($count === 1) {
            return '1 licença renovada com sucesso.';
        }

        return "{$count} licenças renovadas com sucesso.";
    }

    public function update(array $data): ServiceResponse
    {
        try {
            DB::beginTransaction();
            $message = 'Licença atualizada com sucesso.';
            $license = License::findOrFail($data['id']);

            if (isset($data['status'])) {
                $message = $this->handleUpdateStatus($license, $data['status']);
                DB::commit();
                return ServiceResponse::success([], $message);
            }

            unset($data['id']);
            $user = $license->user;
            $user->updateOrFail([
                'code'  => $data['code'],
                'login' => $data['login'],
            ]);

            $license->updateOrFail([
                'start_at'   => $data['start_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'lifetime'   => $data['lifetime'],
                'price'      => $data['price'],
                'indication' => $data['indication'] ?? null,
            ]);
            $this->expiredLicensesCheck();

            DB::commit();
            return ServiceResponse::success([], $message);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return ServiceResponse::error($e, 404, 'Licença não encontrada.');
        } catch (\Throwable|\Exception $e) {
            DB::rollBack();
            return ServiceResponse::error($e);
        }
    }

    public function metrics(array $data): ServiceResponse
    {
        try {
            $license = License::where('uuid', $data['uuid'])->firstOrFail();

            if ($license->cambistas_ativos_count !== $data['metrics_count']) {
                $license->updateOrFail([
                    'cambistas_ativos_count' => $data['metrics_count']
                ]);
            }

            return ServiceResponse::success([], 'Metrics updated successfully.');
        } catch (ModelNotFoundException $e) {
            return ServiceResponse::error($e, 404, $e->getMessage());
        } catch (\Throwable|\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    public function renewBatch(array $data): ServiceResponse
    {
        try {
            DB::beginTransaction();

            $licenses = License::whereIn('id', $data['ids'])->get();
            foreach ($licenses as $license) {
                $this->handleRenewLicense($license);
            }

            DB::commit();
            return ServiceResponse::success([], $this->getMessageByLengthLicensesToRenew($data['ids']));
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return ServiceResponse::error($e, 404, 'Licença não encontrada.');
        } catch (\Throwable|\Exception $e) {
            DB::rollBack();
            return ServiceResponse::error($e);
        }
    }

    /**
     * @throws \Throwable
     */
    private function handleUpdateStatus(License $license, string $status): string
    {
        return match ($status) {
            'renew' => $this->handleRenewLicense($license),
            'revoke' => $this->handleRevokeLicense($license),
        };
    }

    /**
     * @throws \Throwable
     */
    private function handleRevokeLicense(License $license): string
    {
        $license->updateOrFail(['status' => 'revoked']);
        event(new LicenseRevokedEvent($license->uuid));

        return 'Licença revogada com sucesso.';
    }

    private function handleRenewLicense(License $license): string
    {
        if ($license->lifetime) {
            $license->update(['status' => 'active']);
            return 'Licença vitalícia renovada com sucesso.';
        }

        $days = Helper::getDaysBetweenDates($license->start_at->toString(), $license->expires_at->toString());

        $now = Carbon::now();

        $newExpires = $now->copy()->addDays($days - 1)->format('Y-m-d');
        $values = [
            'start_at'   => $now->format('Y-m-d'),
            'expires_at' => $newExpires,
            'status'     => 'active',
        ];

        $license->update($values);
        return "Licença renovada para $days dia(s) de uso.";
    }

    public function check(array $data): ServiceResponse
    {
        try {
            $this->helperCheckLicense($data['uuid']);

            return ServiceResponse::success($data, 'Usuário com licença válida.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401, 'Licença expirada ou inexistente.');
        } catch (\Throwable|\Exception $e) {
            return ServiceResponse::error($e);
        }
    }

    /**
     * @throws \Throwable
     * @throws UnauthorizedException
     */
    public function helperCheckLicense(string $uuid): ?License
    {
        $licence = License::where('uuid', $uuid)->firstOrFail();

        //se não existe licença, emite erro
        if (!$licence) {
            throw new UnauthorizedException();
        }

        //licença revogada, ou expirada
        if (!$licence->isValid()) {
            throw new UnauthorizedException();
        }

        $licence->updateOrFail(['last_use' => now()]);

        return $licence;
    }
}
