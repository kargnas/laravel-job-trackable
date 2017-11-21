<?php

namespace App\Modules\JobTrackable;

trait Trackable
{
    /** @var int $statusId */
    protected $statusId;

    /** @var int */
    protected $expireMinutes = 60;

    protected function setProgressMax($value)
    {
        $this->update(['progress_max' => $value]);
    }

    protected function setProgressNow($value, $every = 1)
    {
        if ($value % $every == 0) {
            $this->update(['progress_now' => $value]);
        }
    }

    protected function setInput(array $value)
    {
        $this->update(['input' => $value]);
    }

    protected function setOutput(array $value)
    {
        $this->update(['output' => $value]);
    }

    protected function update(array $data)
    {
        $task = JobStatus::find($this->statusId);

        if ($task != null) {
            return $task->update($data);
        }
    }

    protected function prepareStatus()
    {
        $status = JobStatus::create([
            'type' => static::class,
        ], $this->expireMinutes);

        $this->statusId = $status->id;
    }

    public function getJobStatusId()
    {
        if ($this->statusId == null) {
            throw new \Exception("Failed to get jobStatusId, have you called \$this->prepareStatus() in __construct() of Job?");
        }

        return $this->statusId;
    }

    public function loadJobStatus()
    {
        return JobStatus::find($this->getJobStatusId());
    }

    /**
     * 작업이 완료될 때 까지 Block 을 건다.
     *
     * @param $limitSeconds
     * @return string
     */
    public function waitUntilFinished($limitSeconds)
    {
        // 100ms
        usleep(100000);

        $untilAt = microtime(true) + $limitSeconds;

        while ($untilAt > microtime(true)) {
            $status = $this->loadJobStatus()->status;
            if ($status === JobStatus::STATUS_FINISHED) {
                return $status;
            }

            usleep(300000);
        }

        return $this->loadJobStatus()->status;
    }
}
