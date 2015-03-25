<?php
namespace Blimp\DataAccess;

use Doctrine\Common\NotifyPropertyChanged;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Timestampable\TimestampableListener;
use Gedmo\Blameable\Mapping\Event\BlameableAdapter;

/**
 * The Blameable listener handles the update of
 * dates on creation and update.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class DeferredBlameableListener extends TimestampableListener {
    protected $api;

    /**
     * Get the user value to set on a blameable field
     *
     * @param object $meta
     * @param string $field
     *
     * @return mixed
     */
    public function getUserValue($meta, $field) {
        $token = null;
        if ($this->api->offsetExists('security')) {
            $token = $this->api['security']->getToken();
        }

        $user = $token !== null ? $token->getUser() : null;

        if($user != null) {
            if ($meta->hasAssociation($field)) {
                if (is_object($user)) {
                    return $user;
                }
            } else {
                if (method_exists($token, 'getUsername')) {
                    return (string) $token->getUsername();
                }

                if (method_exists($user, '__toString')) {
                    return $user->__toString();
                }
            }
        }

        return null;
    }

    public function setApi($api) {
        $this->api = $api;
    }

    protected function getNamespace() {
        return 'Gedmo\Blameable';
    }

    /**
     * Updates a field
     *
     * @param object           $object
     * @param BlameableAdapter $ea
     * @param $meta
     * @param $field
     */
    protected function updateField($object, $ea, $meta, $field) {
        $property = $meta->getReflectionProperty($field);
        $oldValue = $property->getValue($object);
        $newValue = $this->getUserValue($meta, $field);

        //if blame is reference, persist object
        if ($meta->hasAssociation($field) && $newValue) {
            $ea->getObjectManager()->persist($newValue);
        }
        $property->setValue($object, $newValue);
        if ($object instanceof NotifyPropertyChanged) {
            $uow = $ea->getObjectManager()->getUnitOfWork();
            $uow->propertyChanged($object, $field, $oldValue, $newValue);
        }
    }
}
