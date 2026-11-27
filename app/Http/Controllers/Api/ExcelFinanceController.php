<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExcelFinanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::with(['patient', 'provider.specialty'])
            ->where('payment_amount', '>', 0);

        if ($request->filled('from')) {
            $query->whereDate('appointment_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('appointment_date', '<=', $request->to);
        }

        $records = $query->orderByDesc('appointment_date')
            ->get()
            ->map(function ($app) {
                $dateStr = $app->appointment_date instanceof Carbon
                    ? $app->appointment_date->format('Y-m-d')
                    : (string) $app->appointment_date;

                return [
                    'appointment_id' => $app->id,
                    'date'           => $dateStr,
                    'patient_masked' => $this->maskName($app->patient->last_registered_name ?? 'Unknown'),
                    'phone_masked'   => $this->maskPhone($app->patient->phone_number ?? ''),
                    'doctor_unit'    => $app->provider->name ?? '-',
                    'specialty'      => $app->provider->specialty->name ?? '-',
                    'amount'         => (float) $app->payment_amount,
                    'status'         => $app->status,
                ];
            });

        return response()->json([
            'status'  => 'success',
            'summary' => [
                'total_revenue' => $records->sum('amount'),
                'total_count'   => $records->count(),
            ],
            'data'    => $records,
        ]);
    }

    private function maskName(string $name): string
    {
        $parts = explode(' ', trim($name));
        return collect($parts)->map(function ($part) {
            return mb_substr($part, 0, 1) . str_repeat('*', max(mb_strlen($part) - 1, 2));
        })->implode(' ');
    }

    private function maskPhone(string $phone): string
    {
        $clean = preg_replace('/\D/', '', $phone);
        if (strlen($clean) < 6) {
            return '***';
        }
        return substr($clean, 0, 3) . '****' . substr($clean, -2);
    }
}