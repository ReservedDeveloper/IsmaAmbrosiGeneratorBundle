<?php

namespace IsmaAmbrosi\Bundle\GeneratorBundle\Generator;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadataInfo;
use Doctrine\ODM\MongoDB\Tools\DocumentGenerator;
use Doctrine\ODM\MongoDB\Tools\DocumentRepositoryGenerator;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Util\Filesystem;

/**
 * Class DoctrineDocumentGenerator
 *
 * @author Ismael Ambrosi<ismaambrosi@gmail.com>
 */
class DoctrineDocumentGenerator extends Generator
{

    /**
     * @var \Symfony\Component\HttpKernel\Util\Filesystem
     */
    private $filesystem;

    /**
     * @var \Doctrine\ODM\MongoDB\DocumentManager
     */
    private $documentManager;

    /**
     * Constructor
     *
     * @param \Symfony\Component\HttpKernel\Util\Filesystem $filesystem
     * @param \Doctrine\ODM\MongoDB\DocumentManager         $documentManager
     */
    public function __construct(Filesystem $filesystem, DocumentManager $documentManager)
    {
        $this->filesystem = $filesystem;
        $this->documentManager = $documentManager;
    }

    public function generate(BundleInterface $bundle, $document, array $fields, $withRepository)
    {
        $config = $this->documentManager->getConfiguration();
        $config->addDocumentNamespace($bundle->getName(), $bundle->getNamespace() . '\\Document');

        $documentClass = $config->getDocumentNamespace($bundle->getName()) . '\\' . $document;
        $documentPath = $bundle->getPath() . '/Document/' . str_replace('\\', '/', $document) . '.php';
        if (file_exists($documentPath)) {
            throw new \RuntimeException(sprintf('Document "%s" already exists.', $documentClass));
        }

        $class = new ClassMetadataInfo($documentClass);
        if ($withRepository) {
            $class->setCustomRepositoryClass($documentClass . 'Repository');
        }

        $class->mapField(array(
            'fieldName' => 'id',
            'type'      => 'integer',
            'id'        => true
        ));
        $class->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);
        foreach ($fields as $field) {
            $class->mapField($field);
        }

        $documentGenerator = $this->getDocumentGenerator();
        $documentCode = $documentGenerator->generateDocumentClass($class);

        $this->filesystem->mkdir(dirname($documentPath));
        file_put_contents($documentPath, $documentCode, LOCK_EX);

        if ($withRepository) {
            $path = $bundle->getPath() . str_repeat('/..', substr_count(get_class($bundle), '\\'));
            $this->getRepositoryGenerator()->writeDocumentRepositoryClass($class->customRepositoryClassName, $path);
        }
    }

    protected function getDocumentGenerator()
    {
        $documentGenerator = new DocumentGenerator();
        $documentGenerator->setGenerateAnnotations(true);
        $documentGenerator->setGenerateStubMethods(true);
        $documentGenerator->setRegenerateDocumentIfExists(false);
        $documentGenerator->setUpdateDocumentIfExists(true);
        $documentGenerator->setNumSpaces(4);

        return $documentGenerator;
    }

    protected function getRepositoryGenerator()
    {
        return new DocumentRepositoryGenerator();
    }
}
