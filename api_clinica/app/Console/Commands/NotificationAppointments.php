<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Mail\NotificationAppoint;
use Illuminate\Support\Facades\Mail;
use App\Models\Appointment\Appointment;

class NotificationAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notification-appointments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notificar al paciente 1 hora antes de su cita medica, por medio de correo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        date_default_timezone_set("America/Lima");
        $now = now();
        $currentDate = $now->format("Y-m-d");
        $appointments = Appointment::whereDate("date_appointment", $currentDate)
                                    ->where("status",1)
                                    ->where("cron_state",1)
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
                    "specialitie_name" => $appointment->specialitie->name,
                    "n_document" => $appointment->patient->n_document,
                    "hour_start_format" => Carbon::parse(date("Y-m-d")." ".$appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_start)->format("h:i A"),
                    "hour_end_format" => Carbon::parse(date("Y-m-d")." ".$appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_end)->format("h:i A"),
                ]);
            }
            $appointment->update(["cron_state" => 2]);
        }
        foreach ($patients as $key => $patient) {
            Mail::to($patient["email"])->send(new NotificationAppoint($patient));
        }
        
        // dd($patients);
    }
}
