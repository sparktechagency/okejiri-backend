<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
    public function attachments()
    {
        return $this->hasMany(ReportAttachment::class, 'report_attachment_id');
    }
}
