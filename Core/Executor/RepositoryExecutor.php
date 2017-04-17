<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Repository;
//use Kaliop\eZMigrationBundle\API\LanguageAwareInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\Core\RepositoryUserSetterTrait;

/**
 * The core manager class that all migration action managers inherit from.
 */
abstract class RepositoryExecutor extends AbstractExecutor //implements LanguageAwareInterface
{
    use RepositoryUserSetterTrait;

    /**
     * Constant defining the default language code
     */
    const DEFAULT_LANGUAGE_CODE = 'eng-GB';

    /**
     * Constant defining the default Admin user ID.
     * @todo inject via config parameter
     */
    const ADMIN_USER_ID = 14;

    /** @todo inject via config parameter */
    const USER_CONTENT_TYPE = 'user';

    /**
     * @var array $dsl The parsed DSL instruction array
     */
    //protected $dsl;

    /** @var array $context The context (configuration) for the execution of the current step */
    //protected $context;

    /**
     * The eZ Publish 5 API repository.
     *
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    /**
     * Language code for current step.
     *
     * @var string
     */
    //private $languageCode;

    /**
     * @var string
     */
    //private $defaultLanguageCode;

    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    // to redefine in subclasses if they don't support all methods, or if they support more...
    protected $supportedActions = array(
        'create', 'update', 'delete'
    );

    public function setRepository(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }

    public function execute(MigrationStep $step)
    {
        // base checks
        parent::execute($step);

        if (!isset($step->dsl['mode'])) {
            throw new \Exception("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        //$this->dsl = $step->dsl;
        //$this->context = $step->context;
        /*if (isset($step->dsl['lang'])) {
            $this->setLanguageCode($step->dsl['lang']);
        }*/

        if (method_exists($this, $action)) {

            $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
            try {
                $output = $this->$action($step);
            } catch (\Exception $e) {
                $this->loginUser($previousUserId);
                throw $e;
            }

            // reset the environment as much as possible as we had found it before the migration
            $this->loginUser($previousUserId);

            return $output;
        } else {
            throw new \Exception("Invalid step definition: value '$action' is not a method of " . get_class($this));
        }
    }

    /**
     * Method that each executor (subclass) has to implement.
     *
     * It is used to set references based on the DSL instructions executed in the current step, for later steps to reuse.
     *
     * @throws \InvalidArgumentException when trying to set a reference to an unsupported attribute.
     * @param $object
     * @return boolean
     */
    abstract protected function setReferences($object, $step);

    /*public function setLanguageCode($languageCode)
    {
        $this->languageCode = $languageCode;
    }*/

    public function getLanguageCode($step)
    {
        return isset($step->dsl['lang']) ? $step->dsl['lang'] : $this->getLanguageCodeFromContext($step->context);
    }

    public function getLanguageCodeFromContext($context)
    {
        return isset($context['defaultLanguageCode']) ? $context['defaultLanguageCode'] : self::DEFAULT_LANGUAGE_CODE;
    }

    /*public function setDefaultLanguageCode($languageCode)
    {
        $this->defaultLanguageCode = $languageCode;
    }

    public function getDefaultLanguageCode()
    {
        return $this->defaultLanguageCode ?: self::DEFAULT_LANGUAGE_CODE;
    }*/

    /**
     * Courtesy code to avoid reimplementing it in every subclass
     * @deprecated will be moved into the reference resolver classes
     */
    protected function resolveReferencesRecursively($match)
    {
        if (is_array($match)) {
            foreach ($match as $condition => $values) {
                $match[$condition] = $this->resolveReferencesRecursively($values);
            }
            return $match;
        } else {
            return $this->referenceResolver->resolveReference($match);
        }
    }
}
