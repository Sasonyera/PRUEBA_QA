<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Appointment\Appointment;

class NotificationAppointmentWasap extends Command
{
    private const TIME_FORMAT = 'h:i A';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notification-appointment-wasap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notificar al paciente 1 hora antes de su cita medica , por medio de whatsap';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->isNotificationsEnabled()) {
            return Command::SUCCESS;
        }

        date_default_timezone_set("America/Lima");
        $now = now();
        $patients = $this->getPatientsToNotify($now);

        foreach ($patients as $patient) {
            $this->sendNotification($patient);
        }

        return Command::SUCCESS;
    }

    private function isNotificationsEnabled(): bool
    {
        $enabled = filter_var(env('WHATSAPP_NOTIFICATIONS_ENABLED', false), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            Log::info('WhatsApp notifications are disabled.');
        }

        return $enabled;
    }

    private function getPatientsToNotify($now)
    {
        $currentDate = $now->format("Y-m-d");
        $appointments = Appointment::whereDate("date_appointment", $currentDate)
            ->where("status", 1)
            ->get();

        $nowTimestamp = $now->timestamp;
        $patients = collect([]);

        foreach ($appointments as $appointment) {
            if (!$this->shouldNotify($appointment, $currentDate, $nowTimestamp)) {
                continue;
            }

            $patients->push($this->buildPatientPayload($appointment));
        }

        return $patients;
    }

    private function shouldNotify($appointment, string $currentDate, int $nowTimestamp): bool
    {
        $hourStart = $appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_start;
        $hourEnd = $appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_end;

        $hourStart = strtotime(Carbon::parse($currentDate . " " . $hourStart)->subHour());
        $hourEnd = strtotime(Carbon::parse($currentDate . " " . $hourEnd)->subHour());

        return $hourStart <= $nowTimestamp && $hourEnd >= $nowTimestamp;
    }

    private function buildPatientPayload($appointment): array
    {
        return [
            "name" => $appointment->patient->name,
            "surname" => $appointment->patient->surname,
            "avatar" => $appointment->avatar ? env("APP_URL") . "storage/" . $appointment->avatar : NULL,
            "email" => $appointment->patient->email,
            "mobile" => $appointment->patient->mobile,
            "doctor_full_name" => $appointment->doctor->name . ' ' . $appointment->doctor->surname,
            "specialitie_name" => $appointment->specialitie->name,
            "n_document" => $appointment->patient->n_document,
            "hour_start_format" => Carbon::parse(date("Y-m-d") . " " . $appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_start)->format(self::TIME_FORMAT),
            "hour_end_format" => Carbon::parse(date("Y-m-d") . " " . $appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_end)->format(self::TIME_FORMAT),
        ];
    }

    private function sendNotification(array $patient): void
    {
        $accessToken = env('WHATSAPP_ACCESS_TOKEN');
        $fbApiUrl = env('WHATSAPP_API_URL');
        $to = $patient["mobile"] ?? null;

        if (!$accessToken || !$fbApiUrl) {
            Log::warning('WhatsApp token or API URL is not configured.');
            return;
        }

        if (!$to) {
            Log::warning('Patient mobile not found for WhatsApp notification.', [
                'patient' => $patient,
            ]);
            return;
        }

        $data = $this->buildMessagePayload($patient, $to);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];

        $this->sendRequest($fbApiUrl, $headers, $data, $to);
    }

    private function buildMessagePayload(array $patient, string $to): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => 'recordatorio',
                'language' => [
                    'code' => 'es_MX',
                ],
                "components" =>  [
                    [
                        "type" =>  "header",
                        "parameters" =>  [
                            [
                                "type" =>  "text",
                                "text" =>  $patient["name"] . ' ' . $patient["surname"],
                            ]
                        ]
                    ],
                    [
                        "type" => "body",
                        "parameters" => [
                            [
                                "type" => "text",
                                "text" =>  $patient["hour_start_format"] . ' ' . $patient["hour_end_format"],
                            ],
                            [
                                "type" => "text",
                                "text" =>  $patient["doctor_full_name"],
                            ],
                        ]
                    ],
                ],
            ],
        ];
    }

    private function sendRequest(string $fbApiUrl, array $headers, array $data, string $to): void
    {
        $ch = curl_init($fbApiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            Log::error('WhatsApp notification failed.', [
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'response' => $response,
                'to' => $to,
            ]);
        }
    }
}
