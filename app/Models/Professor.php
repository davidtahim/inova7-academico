<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professor extends Model
{
    protected $fillable = ['registration', 'name', 'email', 'qualification', 'active', 'chapa', 'lattes', 'max_classes_per_day', 'max_days_per_week'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function availabilities()
    {
        return $this->hasMany(ProfessorAvailability::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_professor');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'professor_subject');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    /**
     * Lattes sempre gravado como endereço completo. A tela envia sem "http(s)://"
     * (o Mod_Security da hospedagem bloqueia formulários com links), e também aceita
     * só o número do currículo.
     */
    public static function normalizeLattes(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{10,20}$/', $value)) {
            return 'http://lattes.cnpq.br/' . $value;
        }

        return preg_match('#^https?://#i', $value) ? $value : 'https://' . ltrim($value, '/');
    }

    public function setLattesAttribute(?string $value): void
    {
        $this->attributes['lattes'] = self::normalizeLattes($value);
    }
}
