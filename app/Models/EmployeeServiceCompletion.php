<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeServiceCompletion extends Model
{
    protected $guarded = ['id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
