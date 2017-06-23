<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use SoftDeletes;

    protected $appends = ['revision'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at', 'user_id', 'id'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            $user = Auth::guard()->user();
            $project->user()->associate($user);
        });

        static::created(function ($project) {
            $version = new Version;
            $version->revision = 1;
            $version->project()->associate($project);
            $version->save();
            // add first empty python file :)
            $file = new File;
            $file->name = '__init__.py';
            $file->content = '';
            $file->version()->associate($version);
            $file->save();
        });

        static::saving(function ($project) {
            $project->slug = str_slug($project->name, '_');
        });

    }

    /**
     * Get the User that owns the Project.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * Get the Versions this Project has.
     *
     * @return HasMany
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Version::class);
    }

    /**
     * @return string
     */
    public function getRevisionAttribute():? string
    {
        $version = $this->versions()->published()->get()->last();
        return is_null($version) ? null : (string)$version->revision;
    }

    /**
     * @return BelongsToMany
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'dependencies', 'project_id', 'depends_on_project_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany
     */
    public function dependants(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'dependencies', 'depends_on_project_id', 'project_id')
            ->withTimestamps();
    }
}
