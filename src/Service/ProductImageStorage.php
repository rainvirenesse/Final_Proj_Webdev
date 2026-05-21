<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ProductImageStorage
{
    public const PUBLIC_PREFIX = 'images/products/';

    /** @var list<string> */
    private const MANAGED_PREFIXES = [
        self::PUBLIC_PREFIX,
        'uploads/products/',
    ];

    public function __construct(
        private readonly string $productImagesDirectory,
        private readonly string $projectDir,
        private readonly SluggerInterface $slugger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(UploadedFile $file): string
    {
        $this->filesystem->mkdir($this->productImagesDirectory);

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension() ?: 'bin';
        $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$extension;

        try {
            $file->move($this->productImagesDirectory, $newFilename);
        } catch (FileException $e) {
            throw new FileException('Could not store the product image: '.$e->getMessage(), 0, $e);
        }

        return self::PUBLIC_PREFIX.$newFilename;
    }

    public function removeIfManaged(?string $storedPath): void
    {
        $absolute = $this->resolveManagedAbsolutePath($storedPath);
        if ($absolute !== null && $this->filesystem->exists($absolute)) {
            $this->filesystem->remove($absolute);
        }
    }

    public function isManagedPath(?string $storedPath): bool
    {
        return $this->resolveManagedAbsolutePath($storedPath) !== null;
    }

    private function resolveManagedAbsolutePath(?string $storedPath): ?string
    {
        if ($storedPath === null || $storedPath === '') {
            return null;
        }

        $storedPath = ltrim($storedPath, '/');

        foreach (self::MANAGED_PREFIXES as $prefix) {
            if (str_starts_with($storedPath, $prefix)) {
                return $this->projectDir.'/public/'.$storedPath;
            }
        }

        return null;
    }
}
