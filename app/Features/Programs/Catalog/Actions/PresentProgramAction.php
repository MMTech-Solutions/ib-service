<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Actions;

use App\Features\Programs\Catalog\DTOs\ProgramDetailData;
use App\Features\Programs\Catalog\Models\Program;

final class PresentProgramAction
{
    public function toDetail(Program $program): ProgramDetailData
    {
        return $program->toDetailData();
    }
}
