<?php

use App\Models\PlayerProfile;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        $fileManipulator = app(FileManipulator::class);
        $mediaRepository = app(MediaRepository::class);

        $mediaRepository->getByModelType(PlayerProfile::class)
            ->each(function (Media $media) use ($fileManipulator): void {
                $fileManipulator->createDerivedFiles($media);

                gc_collect_cycles();
            });
    }
};
