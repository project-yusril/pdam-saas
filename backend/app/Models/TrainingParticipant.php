<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingParticipant extends Model
{
    protected $fillable = ['training_id', 'employee_id', 'attendance_status'];
}
