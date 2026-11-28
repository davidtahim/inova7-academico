<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['code', 'name', 'abbreviation', 'syllabus', 'total_hours', 'presential_hours', 'online_hours', 'practice_hours', 'extension_hours', 'required_software'];

    /** Sigla para a grade compacta: a cadastrada ou uma gerada a partir do nome. */
    public function getShortLabelAttribute(): string
    {
        return $this->abbreviation ?: self::suggestAbbreviation($this->name);
    }

    /** "BANCO DE DADOS II" → "BD2"; "SISTEMAS OPERACIONAIS" → "SO"; "CÁLCULO" → "CAL". */
    public static function suggestAbbreviation(?string $name): string
    {
        $name = (string) $name;
        $words = preg_split('/[^\p{L}\p{N}]+/u', \Illuminate\Support\Str::upper(\Illuminate\Support\Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY);
        $roman = ['I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4', 'V' => '5'];
        $stop = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'E', 'A', 'O', 'EM', 'PARA', 'AO', 'COM'];
        $label = '';
        $suffix = '';
        foreach ($words as $word) {
            if (isset($roman[$word])) {
                $suffix = $roman[$word];
            } elseif (! in_array($word, $stop, true)) {
                $label .= $word[0];
            }
        }
        if (strlen($label) === 1) {
            $label = substr($words[0] ?? $label, 0, 3);
        }

        return substr($label, 0, 5) . $suffix;
    }

    public function professors()
    {
        return $this->belongsToMany(Professor::class, 'professor_subject');
    }

    public function curriculumMatrices()
    {
        return $this->belongsToMany(CurriculumMatrix::class, 'curriculum_subject')->withPivot(['period', 'sequence']);
    }

    /**
     * Obter os vínculos de disciplina para oferta semestral.
     * Relaciona esta disciplina com cada matriz e período específico.
     */
    public function offeringLinks()
    {
        return $this->hasMany(CurriculumMatrixDiscipline::class)->with('curriculumMatrix');
    }

    /**
     * Obter resumo de quantas vezes esta disciplina está vinculada em diferentes matrizes/períodos.
     */
    public function getOfferingCount()
    {
        return $this->offeringLinks()->count();
    }
}
