<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasName, FilamentUser, HasMedia
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles, InteractsWithMedia, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',

        'uuid',
        'enrollment',
        'login',
        'effective_role_id',
        'commissioned_role_id',
        'group_id',
        'person_id',
        'signature_url',

        // user records
        'ordinance',
        'ordinance_date',
        'admission_date',
        'category',
        'class_id',
        'level_id',
        'title'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = [
        'nickname',
        'profile_photo_url',
        'signature_url',
        'impediments'
    ];

    public function getNicknameAttribute()
    {
        return collect(explode('.', $this->login))
            ->map(fn($part) => ucfirst(trim($part)))
            ->implode(' ');
    }

    public function getProfilePhotoUrlAttribute()
    {
        $filePath = "users/{$this->id}.jpg";

        if (Storage::exists($filePath)) {
            return Storage::url($filePath);
        }

        return asset('img/user-default.png');
    }

    public function getSignatureUrlAttribute()
    {
        $filePath = 'signatures/' . $this->uuid . '.jpg';

        if (Storage::exists($filePath)) {
            return Storage::url($filePath);
        }

        return null;
    }

    public function getFilamentName(): string
    {
        return $this->login ?? 'user';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'cpa') {
            return $this->hasRole('cpa');
        }

        if ($panel->getId() === 'admin') {
            return $this->hasRole('dinfo');
        }

        return true;
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function groups() //groups
    {
        return $this->belongsToMany(Group::class, 'group_user');
    }

    public function effective_role()
    {
        return $this->belongsTo(EffectiveRole::class, 'effective_role_id');
    }

    public function commissioned_role()
    {
        return $this->belongsTo(CommissionedRole::class, 'commissioned_role_id');
    }

    public function record() // registro funcional
    {
        // uuid column must be referenced because it is not the primary key in users table
        return $this->hasOne(UserRecord::class, 'user_uuid', 'uuid');
    }

    public function ordinances()
    {
        return $this->person->ordinances();
    }

    public function calendar_occurrences()
    {
        return $this->hasMany(CalendarOccurrence::class, 'user_id');
    }

    public function posts()
    {
        return $this->hasMany(SocialPost::class, 'user_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function hasDocumentCategory(string $type): bool
    {
        $userGroups = $this->groups->pluck('id');
        $documentCategoryIds = DB::table('document_category_group')->whereIn('group_id', $userGroups)->pluck('document_category_id');

        return DocumentCategory::whereIn('id', $documentCategoryIds)->where('type', $type)->exists();
        // return DB::table('document_category_group')->whereIn('group_id', $userGroups)->exists();
    }

    public function canManageDocument(Document $document): bool
    {
        $categoryGroups = $document->category->groups->pluck('name');
        return $this->hasAnyRole($categoryGroups->toArray());
    }

    public function scopeActive($query)
    {
        return $query->where('password', '<>', 'X');
    }

    public function isActive()
    {
        return $this->password !== 'X';
    }

    public function resetPassword()
    {
        $this->password = $this->person->cpf_cnpj;
        $this->save();
    }

    public function setInactive()
    {
        DB::table('users')->where('id', $this->id)->update(['password' => 'X']);
    }

    public function getImpedimentsAttribute()
    {
        // filtra pelo id do usuario no banco
        $portarias = Portaria::whereJsonContains('impediments', [["user_id" => [$this->id]]])->get();
        // filtra pela data no servidor
        $today = now()->toDateString();
        return $portarias->filter(function ($portaria) use ($today) {
            foreach ($portaria->impediments as $impediment) {
                if (
                    $impediment['start_date'] <= $today &&
                    $impediment['end_date'] >= $today
                ) {
                    return true;
                }
            }
            return false;
        });
    }
}
