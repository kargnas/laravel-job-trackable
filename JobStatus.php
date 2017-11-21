<?php

namespace App\Modules\JobTrackable;

/**
 * App\Modules\JobTrackable
 *
 * @property int        $id
 * @property string     $job_id
 * @property string     $type
 * @property string     $queue
 * @property int        $attempts
 * @property int        $progress_now
 * @property int        $progress_max
 * @property int        $ttl
 * @property string     $status
 * @property string     $input
 * @property string     $output
 * @property string     $created_at
 * @property string     $started_at
 * @property string     $finished_at
 * @property-read mixed $is_ended
 * @property-read mixed $is_executing
 * @property-read mixed $is_failed
 * @property-read mixed $is_finished
 * @method static \Illuminate\Database\Query\Builder|static whereAttempts($value)
 * @method static \Illuminate\Database\Query\Builder|static whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|static whereFinishedAt($value)
 * @method static \Illuminate\Database\Query\Builder|static whereId($value)
 * @method static \Illuminate\Database\Query\Builder|static whereInput($value)
 * @method static \Illuminate\Database\Query\Builder|static whereJobId($value)
 * @method static \Illuminate\Database\Query\Builder|static whereOutput($value)
 * @method static \Illuminate\Database\Query\Builder|static whereProgressMax($value)
 * @method static \Illuminate\Database\Query\Builder|static whereProgressNow($value)
 * @method static \Illuminate\Database\Query\Builder|static whereQueue($value)
 * @method static \Illuminate\Database\Query\Builder|static whereStartedAt($value)
 * @method static \Illuminate\Database\Query\Builder|static whereStatus($value)
 * @method static \Illuminate\Database\Query\Builder|static whereType($value)
 * @mixin \Eloquent
 */
class JobStatus extends \Jenssegers\Model\Model
{
    const STATUS_FAILED    = 'failed';
    const STATUS_FINISHED  = 'finished';
    const STATUS_EXECUTING = 'executing';

    public    $dates   = ['started_at', 'finished_at', 'created_at', 'updated_at'];
    protected $guarded = [];

    /**
     * @param string $id
     * @return string
     */
    protected static function getCacheKey($id)
    {
        return "laravel_job_status:" . $id;
    }

    public static function create($data, $expiredAt)
    {
        $self = new static(array_merge([
            'id'  => uniqid(),
            'ttl' => $expiredAt
        ], $data));

        $self->save();

        return $self;
    }

    public static function find($id)
    {
        $data = json_decode(\Cache::get(static::getCacheKey($id)), true);
        return new self(($data ?: []));
    }

    public function update(array $attributes = [], array $options = [])
    {
        if (!$this->id) {
            return false;
        }

        return $this->fill($attributes)->save();
    }

    protected function save()
    {
        return \Cache::set(static::getCacheKey($this->id), json_encode($this->getAttributes()), $this->ttl);
    }

    /* Accessor */
    public function getInputAttribute($value)
    {
        return json_decode($value, true);
    }

    public function getOutputAttribute($value)
    {
        return json_decode($value, true);
    }

    public function getProgressPercentageAttribute()
    {
        return $this->progress_max != 0 ? round(100 * $this->progress_now / $this->progress_max) : 0;
    }

    public function getIsEndedAttribute()
    {
        return in_array($this->status, [static::STATUS_FAILED, static::STATUS_FINISHED]);
    }

    public function getIsFinishedAttribute()
    {
        return in_array($this->status, [static::STATUS_FINISHED]);
    }

    public function getIsFailedAttribute()
    {
        return in_array($this->status, [static::STATUS_FAILED]);
    }

    public function getIsExecutingAttribute()
    {
        return in_array($this->status, [static::STATUS_EXECUTING]);
    }

    /* Mutator */
    public function setInputAttribute($value)
    {
        $this->attributes['input'] = json_encode($value);
    }

    public function setOutputAttribute($value)
    {
        $this->attributes['output'] = json_encode($value);
    }
}
