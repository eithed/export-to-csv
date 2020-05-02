ExportToCsv Action for Laravel Nova
================================

Exports given view (be it Laravel Lens or Resource) to a CSV file

Installation
------------

    composer require eithed/export-to-csv

Usage
-----

Within your view, per Nova documentation use the action:

```php
public function actions(Request $request)
{
    return [
        new Eithed\ExportToCsv($this),
    ];
}

```

By default CSVs are saved within public/exports folder.

Customization
-----

If a custom job needs to be run after export has finished you'll need to extend this class as such:

```php
namespace App\Nova\Actions;

use App\Jobs\NotifyUserOfCompletedExport;

class ExportToCsv extends \Eithed\ExportToCsv\ExportToCsv
{
    protected function onFinish()
    {
        dispatch(new NotifyUserOfCompletedExport($this->user, $this->url));
    }
}

```

Unfortunately because Action itself is serialized this is the only sensible way that came to my mind to customize actions onFinish.
