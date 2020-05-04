<?php

namespace Eithed\ExportToCsv;

use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Fields\BelongsTo;
use ParseCsv\Csv;
use Illuminate\Support\Facades\Queue;

class ExportToCsv extends Action implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    protected $resource;
    protected $path;
    protected $url;
    protected $user;

    public function __construct($resource, string $path = '')
    {
        // data within constructor will be serialized - that's why we can
        // set path here and it won't be changed every time handle method runs
        $this->resource = $resource;

        if (empty($path)) {
            $path = 'exports';
        }

        $filename = sprintf('%s/%s-%s.csv', $path, $resource->uriKey(), time());

        $this->path = public_path($filename);
        $this->url = url($filename);
        $this->user = request()->user();
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $csv = $this->createCsv(collect($this->resource->fields(request())), $models);

        // would be good if Nova\Action itself had a way of tracking that batch has finished
        // but, as it doesn't, we have to check on every call to handle if it's the last job
        // at this point job wasn't popped from the queue, hence == 1
        if (Queue::size($this->queue) == 1) {
            $this->onFinish();
        }
    }

    protected function createCsv(Collection $lens_fields, Collection $models)
    {
        $csv = new Csv();
        $fields = $lens_fields->map(function ($field) {
            return $field->name;
        })->toArray();

        $data = $models->map(function ($model) use ($lens_fields) {

            $this->resource->resource = $model;
            
            return $lens_fields->map(function ($lens_field) use ($model) {
                // have to clone lens field; otherwise, because resolve sets value on field
                // we would be doing assignment via reference, and, if resolveForDisplay would return null
                // then the $field->value would keep value for previous row
                $field = clone $lens_field;

                if ($field instanceof BelongsTo) {
                    $field->resolve($model);
                } else {
                    $field->resolveForDisplay($model);
                }

                return $field->value;
            })->toArray();
        })->toArray();

        $path = dirname($this->path);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        // append fields as first row
        if (!file_exists($this->path)) {
            $csv->save($this->path, [$fields], true);
        }

        $csv->save($this->path, $data, true);

        return $csv;
    }

    protected function onFinish()
    {
    }
}
