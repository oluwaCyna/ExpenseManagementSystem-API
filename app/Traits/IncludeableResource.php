<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait IncludeableResource
{
    protected function shouldInclude(string $relation): bool
    {
        /** @var Request $request */
        $request = request();
        $includes = explode(',', $request->query('include', ''));

        return in_array($relation, $includes, true);
    }
}
