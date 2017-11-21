<?php

namespace App\Modules\JobTrackable;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class JobTrackableServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Add Event listeners
        app(QueueManager::class)->before(function (JobProcessing $event) {
            $this->updateJobStatus($event->job, [
                'status'     => 'executing',
                'job_id'     => $event->job->getJobId(),
                'queue'      => $event->job->getQueue(),
                'started_at' => Carbon::now()
            ]);
        });
        app(QueueManager::class)->after(function (JobProcessed $event) {
            $this->updateJobStatus($event->job, [
                'status'      => 'finished',
                'finished_at' => Carbon::now()
            ]);
        });

        app(QueueManager::class)->failing(function (JobFailed $event) {
            $this->updateJobStatus($event->job, [
                'status'      => 'failed',
                'finished_at' => Carbon::now()
            ]);
        });
        app(QueueManager::class)->exceptionOccurred(function (JobExceptionOccurred $event) {
            $this->updateJobStatus($event->job, [
                'status'      => 'failed',
                'finished_at' => Carbon::now(),
                'output'      => json_encode(['message' => $event->exception->getMessage()])
            ]);
        });
    }

    private function updateJobStatus(Job $job, array $data)
    {
        try {
            $payload = $job->payload();

            /** @var Trackable $job */
            $job = unserialize($payload['data']['command']);

            if (!in_array(Trackable::class, class_uses_recursive(get_class($job)))) {
                return;
            }

            $jobStatusId = $job->getJobStatusId();

            $jobStatus = JobStatus::find($jobStatusId);
            if (!$jobStatus) {
                throw new \Exception("{$jobStatusId} doesn't exists in store.");
            }
            return $jobStatus->update($data);
        } catch (\Exception $e) {
            Log::error('JobStatusError: ' . $e->getMessage());
        }
    }
}
