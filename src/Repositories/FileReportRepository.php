<?php

namespace Justbetter\StatamicStructuredData\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Justbetter\StatamicStructuredData\Data\Report;
use Statamic\Facades\YAML;
use Symfony\Component\Finder\SplFileInfo;

class FileReportRepository extends ReportRepository
{
    public function __construct(
        protected string $path
    ) {
        File::ensureDirectoryExists($this->path);
    }

    public function store(Report $report): Report
    {
        $this->write($report);

        return $report;
    }

    public function update(Report $report): Report
    {
        $this->write($report);

        return $report;
    }

    public function find(string $id): ?Report
    {
        $file = $this->filePath($id);

        if (! File::exists($file)) {
            return null;
        }

        $data = YAML::parse(File::get($file));

        return Report::make(['id' => $id, ...$data]);
    }

    public function allForSite(string $site): Collection
    {
        if (! File::isDirectory($this->path)) {
            return collect();
        }

        return collect(File::files($this->path))
            ->filter(fn (SplFileInfo $file): bool => str($file->getFilename())->endsWith('.yaml'))
            ->map(function (SplFileInfo $file): Report {
                $data = YAML::parse(File::get($file->getPathname()));
                $id = str($file->getFilename())->beforeLast('.yaml')->toString();

                return Report::make(['id' => $id, ...$data]);
            })
            ->filter(fn (Report $report): bool => $report->site === $site)
            ->sortByDesc(fn (Report $report): string => is_string($report->created_at) ? $report->created_at : '')
            ->values();
    }

    public function delete(string $id): void
    {
        $file = $this->filePath($id);

        if (File::exists($file)) {
            File::delete($file);
        }
    }

    public function pruneOlderThan(int $days): int
    {
        if ($days < 1 || ! File::isDirectory($this->path)) {
            return 0;
        }

        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;

        foreach (File::files($this->path) as $file) {
            if (! str($file->getFilename())->endsWith('.yaml')) {
                continue;
            }

            $parsed = YAML::parse(File::get($file->getPathname()));
            $createdAtValue = $parsed['created_at'] ?? null;
            $createdAt = is_string($createdAtValue) || $createdAtValue instanceof \DateTimeInterface
                ? Carbon::parse($createdAtValue)
                : null;

            if ($createdAt === null || $createdAt->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            File::delete($file->getPathname());
            $deleted++;
        }

        return $deleted;
    }

    protected function write(Report $report): void
    {
        File::ensureDirectoryExists($this->path);

        $id = (string) $report->id;
        $data = $report->toArray();
        unset($data['id']);

        File::put($this->filePath($id), YAML::dump($data));
    }

    protected function filePath(string $id): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$id.'.yaml';
    }
}
