<?php

namespace App\Http\Controllers\Admin\Doctor;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Doctor\Specialitie;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Models\Appointment\Appointment;
use Illuminate\Support\Facades\Storage;
use App\Models\Doctor\DoctorScheduleDay;
use App\Http\Resources\User\UserResource;
use App\Models\Doctor\DoctorScheduleHour;
use App\Http\Resources\User\UserCollection;
use App\Models\Doctor\DoctorScheduleJoinHour;
use App\Http\Resources\Appointment\AppointmentCollection;

class DoctorsController extends Controller
{
    private const CACHE_PROFILE_KEY_PREFIX = 'profile_doctor_#';
    private const TIME_FORMAT = 'h:i A';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAnyDoctor',Doctor::class);
        $search = $request->search;

        $users = User::where(DB::raw("CONCAT(users.name,' ',IFNULL(users.surname,''),' ',users.email)"),"like","%".$search."%")
                        // "name","like","%".$search."%"
                        // ->orWhere("surname","like","%".$search."%")
                        // ->orWhere("email","like","%".$search."%")
                        ->orderBy("id","desc")
                        ->whereHas("roles",function($q){
                            $q->where("name","like","%DOCTOR%");
                        })
                        ->get();

        return response()->json([
            "users" => UserCollection::make($users),
        ]);
    }

    public function profile($id){
        $this->authorize('profileDoctor',Doctor::class);
        $cachedRecord = Redis::get(self::CACHE_PROFILE_KEY_PREFIX.$id);
        $data_doctor = [];
        if(isset($cachedRecord)) {
            $data_doctor = json_decode($cachedRecord, FALSE);
        }else{
            $user = User::findOrFail($id);
            $num_appointment = Appointment::where("doctor_id",$id)->count();
            $money_of_appointments = Appointment::where("doctor_id",$id)->sum("amount");
            $num_appointment_pendings = Appointment::where("doctor_id",$id)->where("status",1)->count();
    
            $appointment_pendings = Appointment::where("doctor_id",$id)->where("status",1)->get();
            $appointments = Appointment::where("doctor_id",$id)->get();
    
            $data_doctor = [
                "num_appointment" => $num_appointment,
                "money_of_appointments" => $money_of_appointments,
                "num_appointment_pendings" => $num_appointment_pendings,
                "doctor" => UserResource::make($user),
                "appointment_pendings" => AppointmentCollection::make($appointment_pendings),
                "appointments" => $appointments->map(function($appointment){
                    return [
                        "id" => $appointment->id,
                        "patient" => [
                            "id" => $appointment->patient->id,
                            "full_name" => $appointment->patient->name . ' ' .$appointment->patient->surname,
                            "avatar" => $appointment->patient->avatar ? env("APP_URL")."storage/".$appointment->patient->avatar : 'https://cdn-icons-png.flaticon.com/512/1430/1430453.png',
                        ],
                        "doctor" => [
                            "id" => $appointment->doctor->id,
                            "full_name" => $appointment->doctor->name . ' ' .$appointment->doctor->surname,
                            "avatar" => $appointment->doctor->avatar ? env("APP_URL")."storage/".$appointment->doctor->avatar : NULL,
                        ],
                        "date_appointment" => $appointment->date_appointment,
                        "date_appointment_format" => Carbon::parse($appointment->date_appointment)->format("d M Y"),
                        "format_hour_start" => Carbon::parse(date("Y-m-d").' '.$appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_start)->format(self::TIME_FORMAT),
                        "format_hour_end" => Carbon::parse(date("Y-m-d").' '.$appointment->doctor_schedule_join_hour->doctor_schedule_hour->hour_end)->format(self::TIME_FORMAT),
                        "appointment_attention" => $appointment->attention ? [
                            "id" => $appointment->attention->id,
                            "description" => $appointment->attention->description,
                            "receta_medica" => $appointment->attention->receta_medica ? json_decode($appointment->attention->receta_medica) : [],
                            "created_at" => $appointment->attention->created_at->format("Y-m-d h:i A"),
                        ] : NULL,
                        "amount" => $appointment->amount,
                        "status_pay" => $appointment->status_pay,
                        "status" => $appointment->status,
                    ];
                }),
            ];
            Redis::set(self::CACHE_PROFILE_KEY_PREFIX.$id, json_encode($data_doctor),'EX', 3600);
        }

        return response()->json($data_doctor);
    }

    public function config() {
        $roles = Role::where("name","like","%DOCTOR%")->get();

        $specialities = Specialitie::where("state",1)->get();

        $hours_days = collect([]);

        $doctor_schedule_hours = DoctorScheduleHour::all();
        foreach ($doctor_schedule_hours->groupBy("hour") as $key => $schedule_hour) {
            $hours_days->push([
                "hour" => $key,
                "format_hour" => Carbon::parse(date("Y-m-d").' '.$key.":00:00")->format(self::TIME_FORMAT),
                "items" => $schedule_hour->map(function($hour_item) {
                    // Y-m-d h:i:s 2023-10-2 00:13:30 -> 12:13:20
                    return [
                        "id" => $hour_item->id,
                        "hour_start" => $hour_item->hour_start,
                        "hour_end" => $hour_item->hour_end,
                        "format_hour_start" => Carbon::parse(date("Y-m-d").' '.$hour_item->hour_start)->format(self::TIME_FORMAT),
                        "format_hour_end" => Carbon::parse(date("Y-m-d").' '.$hour_item->hour_end)->format(self::TIME_FORMAT),
                        "hour" => $hour_item->hour,
                    ];
                }),
            ]);
        }
        return response()->json([
            "roles" => $roles,
            "specialities" => $specialities,
            "hours_days" => $hours_days,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('createDoctor',Doctor::class);
        $schedule_hours = json_decode($request->schedule_hours,1);

        $users_is_valid = User::where("email",$request->email)->first();

        if($users_is_valid){
            return response()->json([
                "message" => 403,
                "message_text" => "EL USUARIO CON ESTE EMAIL YA EXISTE"
            ]);
        }

        if($request->hasFile("imagen")){
            $path = Storage::putFile("staffs",$request->file("imagen"));
            $request->request->add(["avatar" => $path]);
        }

        if($request->password){
            $request->request->add(["password" => bcrypt($request->password)]);
        }
        // "Fri Oct 08 1993 00:00:00 GMT-0500 (hora estándar de Perú)"
        // Eliminar la parte de la zona horaria (GMT-0500 y entre paréntesis)
        $date_clean = preg_replace('/\(.*\)|[A-Z]{3}-\d{4}/', '', $request->birth_date);

        $request->request->add(["birth_date" => Carbon::parse($date_clean)->format("Y-m-d h:i:s")]);

        $user = User::create($request->all());

        $role = Role::findOrFail($request->role_id);
        $user->assignRole($role);

        // ALMACENAR LA DISPONIBILIDAD DE HORARIO DEL DOCTOR

        foreach ($schedule_hours as $key => $schedule_hour) {
            if(sizeof($schedule_hour["children"]) > 0){
                $schedule_day = DoctorScheduleDay::create([
                    "user_id" => $user->id,
                    "day" => $schedule_hour["day_name"],
                ]);
    
                foreach ($schedule_hour["children"] as $children) {
                    DoctorScheduleJoinHour::create([
                        "doctor_schedule_day_id" => $schedule_day->id,
                        "doctor_schedule_hour_id" => $children["item"]["id"],
                    ]);
                }
            }
        }
        return response()->json([
            "message" => 200
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $this->authorize('viewDoctor',Doctor::class);
        $user = User::findOrFail($id);

        return response()->json([
            "doctor" => UserResource::make($user), 
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('updateDoctor',Doctor::class);
        $schedule_hours = json_decode($request->schedule_hours,1) ?? [];
        
        $users_is_valid = User::where("id","<>",$id)->where("email",$request->email)->first();

        if($users_is_valid){
            return response()->json([
                "message" => 403,
                "message_text" => "EL USUARIO CON ESTE EMAIL YA EXISTE"
            ]);
        }

        $user = User::findOrFail($id);

        $this->applyAvatar($request, $user);
        $this->applyPassword($request);
        $this->applyBirthDate($request);

        // $request->request->add(["birth_date" => Carbon::parse($request->birth_date, 'GMT')->format("Y-m-d h:i:s")]);
        $cachedRecord = Redis::get(self::CACHE_PROFILE_KEY_PREFIX.$id);
        if(isset($cachedRecord)) {
            Redis::del(self::CACHE_PROFILE_KEY_PREFIX.$id);
        }
        $user->update($request->all());
        $this->syncRole($request, $user);
        $this->syncScheduleHours($user, $schedule_hours);

        return response()->json([
            "message" => 200
        ]);

    }

    private function applyAvatar(Request $request, User $user): void
    {
        if(!$request->hasFile("imagen")){
            return;
        }
        if($user->avatar){
            Storage::delete($user->avatar);
        }
        $path = Storage::putFile("staffs",$request->file("imagen"));
        $request->request->add(["avatar" => $path]);
    }

    private function applyPassword(Request $request): void
    {
        if($request->password){
            $request->request->add(["password" => bcrypt($request->password)]);
        }
    }

    private function applyBirthDate(Request $request): void
    {
        if(!$request->birth_date){
            return;
        }
        $date_clean = preg_replace('/\(.*\)|[A-Z]{3}-\d{4}/', '', $request->birth_date);
        $request->request->add(["birth_date" => Carbon::parse($date_clean)->format("Y-m-d h:i:s")]);
    }

    private function syncRole(Request $request, User $user): void
    {
        if($request->role_id == $user->roles()->first()->id){
            return;
        }
        $role_old = Role::findOrFail($user->roles()->first()->id);
        $user->removeRole($role_old);

        $role_new = Role::findOrFail($request->role_id);
        $user->assignRole($role_new);
    }

    private function syncScheduleHours(User $user, array $schedule_hours): void
    {
        $this->removeMissingScheduleDays($user, $schedule_hours);
        $this->addMissingScheduleDays($user, $schedule_hours);
    }

    private function removeMissingScheduleDays(User $user, array $schedule_hours): void
    {
        foreach ($user->schedule_days as $schedule_day) {
            $schedule_hour = $this->findScheduleHourByDay($schedule_hours, $schedule_day->day);
            if(!$schedule_hour || !$this->hasChildren($schedule_hour)){
                $this->deleteScheduleDay($schedule_day);
                continue;
            }
            $this->removeMissingScheduleSegments($schedule_day, $schedule_hour);
        }
    }

    private function removeMissingScheduleSegments($schedule_day, array $schedule_hour): void
    {
        foreach ($schedule_day->schedules_hours as $schedules_hour) {
            $exists = $this->scheduleHourHasChild($schedule_hour, $schedules_hour->doctor_schedule_hour_id);
            if(!$exists){
                $schedules_hour->delete();
            }
        }
    }

    private function addMissingScheduleDays(User $user, array $schedule_hours): void
    {
        foreach ($schedule_hours as $schedule_hour) {
            if(!$this->hasChildren($schedule_hour)){
                continue;
            }
            $schedule_day = $this->findScheduleDayByName($user, $schedule_hour["day_name"]);
            if($schedule_day){
                $this->addMissingScheduleSegments($schedule_day, $schedule_hour);
                continue;
            }
            $schedule_day = DoctorScheduleDay::create([
                "user_id" => $user->id,
                "day" => $schedule_hour["day_name"],
            ]);
            $this->createScheduleSegments($schedule_day, $schedule_hour);
        }
    }

    private function addMissingScheduleSegments($schedule_day, array $schedule_hour): void
    {
        foreach ($schedule_hour["children"] as $children) {
            $exists = $this->scheduleDayHasHour($schedule_day, $children["item"]["id"]);
            if(!$exists){
                DoctorScheduleJoinHour::create([
                    "doctor_schedule_day_id" => $schedule_day->id,
                    "doctor_schedule_hour_id" => $children["item"]["id"],
                ]);
            }
        }
    }

    private function createScheduleSegments($schedule_day, array $schedule_hour): void
    {
        foreach ($schedule_hour["children"] as $children) {
            DoctorScheduleJoinHour::create([
                "doctor_schedule_day_id" => $schedule_day->id,
                "doctor_schedule_hour_id" => $children["item"]["id"],
            ]);
        }
    }

    private function deleteScheduleDay($schedule_day): void
    {
        foreach ($schedule_day->schedules_hours as $schedules_hour) {
            $schedules_hour->delete();
        }
        $schedule_day->delete();
    }

    private function findScheduleHourByDay(array $schedule_hours, string $day): ?array
    {
        foreach ($schedule_hours as $schedule_hour) {
            if(($schedule_hour["day_name"] ?? null) === $day){
                return $schedule_hour;
            }
        }
        return null;
    }

    private function findScheduleDayByName(User $user, string $day)
    {
        foreach ($user->schedule_days as $schedule_day) {
            if($schedule_day->day == $day){
                return $schedule_day;
            }
        }
        return null;
    }

    private function hasChildren(array $schedule_hour): bool
    {
        return sizeof($schedule_hour["children"]) > 0;
    }

    private function scheduleHourHasChild(array $schedule_hour, int $doctorScheduleHourId): bool
    {
        foreach ($schedule_hour["children"] as $children) {
            if($doctorScheduleHourId == $children["item"]["id"]){
                return true;
            }
        }
        return false;
    }

    private function scheduleDayHasHour($schedule_day, int $doctorScheduleHourId): bool
    {
        foreach ($schedule_day->schedules_hours as $schedules_hour) {
            if($schedules_hour->doctor_schedule_hour_id == $doctorScheduleHourId){
                return true;
            }
        }
        return false;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('deleteDoctor',Doctor::class);
        $user = User::findOrFail($id);
        $user->delete();
        $cachedRecord = Redis::get(self::CACHE_PROFILE_KEY_PREFIX.$id);
        if(isset($cachedRecord)) {
            Redis::del(self::CACHE_PROFILE_KEY_PREFIX.$id);
        }
        return response()->json([
            "message" => 200
        ]);
    }
}
