<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Appointment\Appointment;

class NotificationAppointmentWasap extends Command
{
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
        if (!filter_var(env('WHATSAPP_NOTIFICATIONS_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            Log::info('WhatsApp notifications are disabled.');
            return Command::SUCCESS;
        }

        date_default_timezone_set("America/Lima");
        $now = now();
        $currentDate = $now->format("Y-m-d");
        $appointments = Appointment::whereDate("date_appointment", $currentDate)
                                    ->where("status", 1)
                                    ->get();
        $now_time_number = $now->timestamp;
        $patients = collect([]);
        foreach ($appointments as $key => $appointment) {
            $hour_start = $appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_start;
            $hour_end = $appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_end;
            
            $hour_start = strtotime(Carbon::parse($currentDate." ".$hour_start)->subHour());
            $hour_end = strtotime(Carbon::parse($currentDate." ".$hour_end)->subHour());
           
            if($hour_start <= $now_time_number && $hour_end >= $now_time_number){
                $patients->push([
                    "name" => $appointment->patient->name,
                    "surname" => $appointment->patient->surname,
                    "avatar" => $appointment->avatar ? env("APP_URL")."storage/".$appointment->avatar : NULL,
                    "email" => $appointment->patient->email,
                    "mobile" => $appointment->patient->mobile,
                    "doctor_full_name" => $appointment->doctor->name.' '.$appointment->doctor->surname,
                    "specialitie_name" => $appointment->specialitie->name,
                    "n_document" => $appointment->patient->n_document,
                    "hour_start_format" => Carbon::parse(date("Y-m-d")." ".$appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_start)->format("h:i A"),
                    "hour_end_format" => Carbon::parse(date("Y-m-d")." ".$appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_end)->format("h:i A"),
                ]);
            }
        }

        foreach ($patients as $key => $patient) {
            $accessToken = env('WHATSAPP_ACCESS_TOKEN');
            $fbApiUrl = env('WHATSAPP_API_URL');
            $to = $patient["mobile"] ?? null;

            if (!$accessToken || !$fbApiUrl) {
                Log::warning('WhatsApp token or API URL is not configured.');
                continue;
            }

            if (!$to) {
                Log::warning('Patient mobile not found for WhatsApp notification.', [
                    'patient' => $patient,
                ]);
                continue;
            }
        
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => 'recordatorio',
                    'language' => [
                        'code' => 'es_MX',
                    ],
                    "components"=>  [
                        [
                            "type" =>  "header",
                            "parameters"=>  [
                                [
                                    "type"=>  "text",
                                    "text"=>  $patient["name"].' '.$patient["surname"],
                                ]
                            ]
                        ],
                        [
                            "type" => "body",
                            "parameters" => [
                                [
                                    "type"=> "text",
                                    "text"=>  $patient["hour_start_format"].' '. $patient["hour_end_format"],
                                ],
                                [
                                    "type"=> "text",
                                    "text"=>  $patient["doctor_full_name"]
                                ],
                            ] 
                        ],
                    ],
                ],
            ];
            
            $headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ];
            
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

        return Command::SUCCESS;
    }
}
