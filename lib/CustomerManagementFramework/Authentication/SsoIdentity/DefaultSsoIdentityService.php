<?php

namespace CustomerManagementFramework\Authentication\SsoIdentity;

use CustomerManagementFramework\CustomerProvider\CustomerProviderInterface;
use CustomerManagementFramework\Model\CustomerInterface;
use CustomerManagementFramework\Model\SsoAwareCustomerInterface;
use CustomerManagementFramework\Model\SsoIdentityInterface;
use CustomerManagementFramework\Traits\LoggerAware;
use Pimcore\Db;
use Pimcore\File;
use Pimcore\Model\Object\Concrete;
use Pimcore\Model\Object\SsoIdentity;

/**
 * SSO identity service handling SsoIdentities as objects on a SsoAwareCustomerInterface
 */
class DefaultSsoIdentityService implements SsoIdentityServiceInterface
{
    use LoggerAware;

    /**
     * @var CustomerProviderInterface
     */
    protected $customerProvider;

    /**
     * @param CustomerProviderInterface $customerProvider
     */
    public function __construct(CustomerProviderInterface $customerProvider)
    {
        $this->customerProvider = $customerProvider;
    }

    /**
     * @param string $provider
     * @param string $identifier
     * @return CustomerInterface|null
     */
    public function getCustomerBySsoIdentity($provider, $identifier)
    {
        $ssoIdentity = $this->findSsoIdentity($provider, $identifier);
        if ($ssoIdentity) {
            return $this->findCustomerBySsoIdentity($ssoIdentity);
        }
    }

    /**
     * @param $provider
     * @param $identifier
     * @return SsoIdentityInterface
     */
    protected function findSsoIdentity($provider, $identifier)
    {
        $list = new SsoIdentity\Listing();
        $list->addConditionParam('provider = ?', $provider);
        $list->addConditionParam('identifier = ?', $identifier);

        if ($list->count() === 1) {
            return $list->current();
        }

        if ($list->count() > 1) {
            $exception = new \RuntimeException(sprintf('Ambiguous results: found more than one identity for %s:%s', $provider, $identifier));
            $this->getLogger()->error($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @param SsoIdentityInterface $ssoIdentity
     * @return CustomerInterface|null
     */
    protected function findCustomerBySsoIdentity(SsoIdentityInterface $ssoIdentity)
    {
        $select = Db::get()
            ->select()
            ->from(sprintf('object_relations_%d', $this->customerProvider->getCustomerClassId()), ['src_id'])
            ->where('fieldname = ?', 'ssoIdentities')
            ->where('dest_id = ?', $ssoIdentity->getId());

        $stmt   = $select->query();
        $result = $stmt->fetchAll();

        if (count($result) === 1) {
            return $this->customerProvider->getById((int)$result[0]['src_id']);
        }
    }

    /**
     * @param CustomerInterface|SsoAwareCustomerInterface $customer
     * @return SsoIdentityInterface[]
     */
    public function getSsoIdentities(CustomerInterface $customer)
    {
        $this->checkCustomer($customer);

        if (empty($customer->getSsoIdentities())) {
            return [];
        }

        return $customer->getSsoIdentities();
    }

    /**
     * @param CustomerInterface $customer
     * @param string $provider
     * @param string $identifier
     * @return SsoIdentityInterface|null
     */
    public function getSsoIdentity(CustomerInterface $customer, $provider, $identifier)
    {
        foreach ($this->getSsoIdentities($customer) as $ssoIdentity) {
            if ($ssoIdentity->getProvider() === $provider && $ssoIdentity->getIdentifier() === $identifier) {
                return $ssoIdentity;
            }
        }
    }

    /**
     * @param CustomerInterface|SsoAwareCustomerInterface $customer
     * @param SsoIdentityInterface $ssoIdentity
     * @return $this
     */
    public function addSsoIdentity(CustomerInterface $customer, SsoIdentityInterface $ssoIdentity)
    {
        $this->checkCustomer($customer);

        $ssoIdentities = $this->getSsoIdentities($customer);
        $ssoIdentities[] = $ssoIdentity;

        $customer->setSsoIdentities(array_unique($ssoIdentities));
    }

    /**
     * @param CustomerInterface|Concrete $customer
     * @param string $provider
     * @param string $identifier
     * @param mixed $profileData
     * @return SsoIdentityInterface
     */
    public function createSsoIdentity(CustomerInterface $customer, $provider, $identifier, $profileData)
    {
        $key  = File::getValidFilename(sprintf('%s-%s', $provider, $identifier));
        $path = sprintf('%s/%s', $customer->getRealFullPath(), $key);

        $ssoIdentity = SsoIdentity::getByPath($path);
        if (!$ssoIdentity) {
            $ssoIdentity = new SsoIdentity();
        }

        $ssoIdentity->setPublished(true);
        $ssoIdentity->setKey($key);
        $ssoIdentity->setParent($customer);
        $ssoIdentity->setProvider($provider);
        $ssoIdentity->setIdentifier($identifier);
        $ssoIdentity->setProfileData($profileData);

        return $ssoIdentity;
    }

    /**
     * @param CustomerInterface $customer
     */
    protected function checkCustomer(CustomerInterface $customer)
    {
        if (!$customer instanceof SsoAwareCustomerInterface) {
            throw new \RuntimeException('Customer needs to implement SsoAwareCustomerInterface');
        }
    }
}