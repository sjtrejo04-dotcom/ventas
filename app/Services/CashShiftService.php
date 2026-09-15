<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;

class CashShiftService
{
    /**
     * Open a new cash shift for a specific register and user.
     *
     * @throws DomainException
     */
    public function openShift(int $cashRegisterId, int $userId, float $openingBs, float $openingUsd): CashShift
    {
        $register = CashRegister::findOrFail($cashRegisterId);

        if (! $register->is_active) {
            throw new DomainException("La caja {$register->code} no se encuentra activa.");
        }

        // Check if register already has an open shift
        $existingRegisterShift = CashShift::where('cash_register_id', $cashRegisterId)
            ->where('status', 'open')
            ->first();

        if ($existingRegisterShift) {
            throw new DomainException("La caja {$register->code} ya tiene un turno abierto (Turno #{$existingRegisterShift->id}).");
        }

        // Check if user already has an open shift anywhere
        $existingUserShift = CashShift::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if ($existingUserShift) {
            throw new DomainException("El usuario ya tiene un turno abierto activo (Turno #{$existingUserShift->id}).");
        }

        return CashShift::create([
            'cash_register_id' => $cashRegisterId,
            'user_id' => $userId,
            'opened_at' => Carbon::now(),
            'status' => 'open',
            'opening_cash_bs' => $openingBs,
            'opening_cash_usd' => $openingUsd,
            'system_cash_bs' => $openingBs,
            'system_cash_usd' => $openingUsd,
            'system_pos_bs' => 0,
            'system_mobile_pay_bs' => 0,
            'system_cashea_bs' => 0,
        ]);
    }

    /**
     * Force close an existing open shift on a register and open a new one.
     *
     * @throws DomainException
     */
    public function forceCloseAndOpenShift(int $cashRegisterId, int $userId, float $openingBs, float $openingUsd): CashShift
    {
        $existingRegisterShift = $this->getActiveShiftForRegister($cashRegisterId);

        if ($existingRegisterShift) {
            // Force close it with system totals (0 difference)
            $this->closeShift($existingRegisterShift, [
                'notes' => 'Cierre forzado automático por apertura de nuevo turno.',
            ]);
        }

        // Now open the new shift
        return $this->openShift($cashRegisterId, $userId, $openingBs, $openingUsd);
    }

    /**
     * Close an active shift and perform blind arqueo audit.
     *
     * @param  array<string, mixed>  $declaredAmounts
     *
     * @throws DomainException
     */
    public function closeShift(CashShift $shift, array $declaredAmounts): CashShift
    {
        if ($shift->isClosed()) {
            throw new DomainException("El turno #{$shift->id} ya se encuentra cerrado.");
        }

        // Calculate system totals from sales and initial opening cash
        $systemTotals = $this->calculateSystemTotals($shift);

        $declaredCashBs = (float) ($declaredAmounts['declared_cash_bs'] ?? $declaredAmounts['cash_bs'] ?? 0);
        $declaredCashUsd = (float) ($declaredAmounts['declared_cash_usd'] ?? $declaredAmounts['cash_usd'] ?? 0);
        $declaredPosBs = (float) ($declaredAmounts['declared_pos_bs'] ?? $declaredAmounts['pos_bs'] ?? 0);
        $declaredMobilePayBs = (float) ($declaredAmounts['declared_mobile_pay_bs'] ?? $declaredAmounts['mobile_pay_bs'] ?? 0);
        $notes = $declaredAmounts['notes'] ?? null;

        // Accounting difference = Declared - System (positive = surplus, negative = shortage)
        $differenceCashBs = round($declaredCashBs - $systemTotals['cash_bs'], 2);
        $differenceCashUsd = round($declaredCashUsd - $systemTotals['cash_usd'], 2);

        $shift->update([
            'closed_at' => Carbon::now(),
            'status' => 'closed',
            'system_cash_bs' => $systemTotals['cash_bs'],
            'system_cash_usd' => $systemTotals['cash_usd'],
            'system_pos_bs' => $systemTotals['pos_bs'],
            'system_mobile_pay_bs' => $systemTotals['mobile_pay_bs'],
            'system_cashea_bs' => $systemTotals['cashea_bs'],
            'declared_cash_bs' => $declaredCashBs,
            'declared_cash_usd' => $declaredCashUsd,
            'declared_pos_bs' => $declaredPosBs,
            'declared_mobile_pay_bs' => $declaredMobilePayBs,
            'difference_cash_bs' => $differenceCashBs,
            'difference_cash_usd' => $differenceCashUsd,
            'notes' => $notes,
        ]);

        return $shift->fresh();
    }

    /**
     * Calculate current expected system totals for an active shift.
     *
     * @return array<string, float>
     */
    public function calculateSystemTotals(CashShift $shift): array
    {
        $cashBs = (float) $shift->opening_cash_bs;
        $cashUsd = (float) $shift->opening_cash_usd;
        $posBs = 0.0;
        $mobilePayBs = 0.0;
        $casheaBs = 0.0;

        $sales = $shift->sales()->with('payments.paymentMethod')->get();

        foreach ($sales as $sale) {
            foreach ($sale->payments as $payment) {
                $amount = (float) $payment->amount;
                $methodName = strtolower(trim((string) ($payment->paymentMethod?->name ?? '')));

                if (str_contains($methodName, 'divisa') || str_contains($methodName, 'usd') || ($payment->paymentMethod?->applies_igtf ?? false)) {
                    $cashUsd += $amount;
                } elseif (str_contains($methodName, 'punto') || str_contains($methodName, 'pos') || str_contains($methodName, 'debito')) {
                    $posBs += $amount;
                } elseif (str_contains($methodName, 'movil') || str_contains($methodName, 'móvil')) {
                    $mobilePayBs += $amount;
                } elseif (str_contains($methodName, 'cashea')) {
                    $casheaBs += $amount;
                } else {
                    // Default Bolívares cash or general cash
                    $cashBs += $amount;
                }
            }
        }

        return [
            'cash_bs' => round($cashBs, 2),
            'cash_usd' => round($cashUsd, 2),
            'pos_bs' => round($posBs, 2),
            'mobile_pay_bs' => round($mobilePayBs, 2),
            'cashea_bs' => round($casheaBs, 2),
        ];
    }

    /**
     * Get the active shift for a given user, if any.
     */
    public function getActiveShiftForUser(int $userId): ?CashShift
    {
        return CashShift::where('user_id', $userId)
            ->where('status', 'open')
            ->first();
    }

    /**
     * Get the active shift for a given cash register, if any.
     */
    public function getActiveShiftForRegister(int $cashRegisterId): ?CashShift
    {
        return CashShift::where('cash_register_id', $cashRegisterId)
            ->where('status', 'open')
            ->first();
    }
}
