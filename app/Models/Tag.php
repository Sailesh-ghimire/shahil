<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $color
 */
class Tag extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'name',
        'color',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => 'gray',
    ];

    /**
     * @return MorphToMany<Contact, $this>
     */
    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'taggable');
    }

    /**
     * @return MorphToMany<Company, $this>
     */
    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'taggable');
    }
}
