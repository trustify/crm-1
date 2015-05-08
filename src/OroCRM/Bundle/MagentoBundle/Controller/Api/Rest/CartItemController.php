<?php

namespace OroCRM\Bundle\MagentoBundle\Controller\Api\Rest;

use Doctrine\ORM\EntityNotFoundException;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\SoapBundle\Controller\Api\Rest\RestController;
use Oro\Bundle\SecurityBundle\Exception\ForbiddenException;

use OroCRM\Bundle\MagentoBundle\Entity\Cart;
use OroCRM\Bundle\MagentoBundle\Entity\Manager\CartApiEntityManager;
use OroCRM\Bundle\MagentoBundle\Entity\CartItem;

/**
 * @NamePrefix("oro_api_")
 */
class CartItemController extends RestController implements ClassResourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function getManager()
    {
        return $this->get('orocrm_magento.cart_item.manager.api');
    }

    /**
     * {@inheritdoc}
     */
    public function getForm()
    {
        return $this->get('orocrm_magento.form.type.cart_item.api');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormHandler()
    {
        return $this->get('orocrm_magento.form.handler.cart_item');
    }

    /**
     * REST Add item to the cart
     *
     * @ApiDoc(
     *      description="Add item to cart",
     *      resource=true
     * )
     * @Acl(
     *      id="orocrm_magento_cart_item_create",
     *      type="entity",
     *      permission="CREATE",
     *      class="OroCRMMagentoBundle:CartItem"
     * )
     * @param int $cartId
     *
     * @return JsonResponse
     */
    public function postAction($cartId)
    {
        /** @var Cart $cart */
        $cart        = $this->getCartManager()->find($cartId);
        $isProcessed = false;
        $entity      = new CartItem();

        if (!empty($cart)) {
            $isProcessed = $this->processForm($entity);

            if (true === $isProcessed) {
                $view = $this->view($this->createResponseData($entity), Codes::HTTP_CREATED);
            } else {
                $view = $this->view($this->getForm(), Codes::HTTP_BAD_REQUEST);
            }
        } else {
            $view = $this->view($this->getForm(), Codes::HTTP_NOT_FOUND);
        }

        return $this->buildResponse($view, self::ACTION_CREATE, ['success' => $isProcessed, 'entity' => $entity]);
    }

    /**
     * REST GET cart item
     *
     * @param int $cartId
     * @param int $itemId
     *
     * @ApiDoc(
     *      description="Get cart item",
     *      resource=true
     * )
     * @AclAncestor("orocrm_magento_cart_view")
     *
     * @return Response
     */
    public function getAction($cartId, $itemId)
    {
        $cartItem = $this->getManager()->getSpecificSerializedItem($cartId, $itemId);

        return new JsonResponse(
            $cartItem,
            empty($cartItem) ? Codes::HTTP_NOT_FOUND : Codes::HTTP_OK
        );
    }

    /**
     * REST GET list
     *
     * @ApiDoc(
     *      description="Get all cart items",
     *      resource=true
     * )
     * @AclAncestor("orocrm_magento_cart_view")
     *
     * @param int $cartId
     *
     * @return JsonResponse
     */
    public function cgetAction($cartId)
    {
        $cartItems = $this->getManager()->getAllSerializedItems($cartId);

        return new JsonResponse(
            $cartItems,
            empty($cartItems) ? Codes::HTTP_NOT_FOUND : Codes::HTTP_OK
        );
    }

    /**
     * REST PUT
     *
     * @param int $itemId cart item id
     * @param int $cartId cart id
     *
     * @ApiDoc(
     *      description="Update cart item",
     *      resource=true
     * )
     * @Acl(
     *      id="orocrm_magento_cart_item_update",
     *      type="entity",
     *      permission="EDIT",
     *      class="OroCRMMagentoBundle:CartItem"
     * )
     * @return Response
     */
    public function putAction($cartId, $itemId)
    {
        $cartItem = $this->getManager()->findOneBy(['cart' => $cartId, 'id' => $itemId]);

        if ($cartItem) {
            if ($this->processForm($cartItem)) {
                $view = $this->view(null, Codes::HTTP_NO_CONTENT);
            } else {
                $view = $this->view($this->getForm(), Codes::HTTP_BAD_REQUEST);
            }
        } else {
            $view = $this->view(null, Codes::HTTP_NOT_FOUND);
        }

        return $this->buildResponse($view, self::ACTION_UPDATE, ['id' => $itemId, 'entity' => $cartItem]);
    }

    /**
     * REST DELETE
     *
     * @param int $itemId item id
     * @param int $cartId cart id
     *
     * @ApiDoc(
     *      description="Delete cart item",
     *      resource=true
     * )
     * @Acl(
     *      id="orocrm_magento_cart_item_delete",
     *      type="entity",
     *      permission="DELETE",
     *      class="OroCRMMagentoBundle:CartItem"
     * )
     * @return Response
     */
    public function deleteAction($cartId, $itemId)
    {
        $isProcessed = false;

        $cartItem = $this->getManager()->findOneBy(['cart' => $cartId, 'id' => $itemId]);

        if (!$cartItem) {
            $view = $this->view(null, Codes::HTTP_NOT_FOUND);
        } else {
            try {
                $this->getDeleteHandler()->handleDelete($itemId, $this->getManager());

                $isProcessed = true;
                $view        = $this->view(null, Codes::HTTP_NO_CONTENT);
            } catch (EntityNotFoundException $notFoundEx) {
                $view = $this->view(null, Codes::HTTP_NOT_FOUND);
            } catch (ForbiddenException $forbiddenEx) {
                $view = $this->view(['reason' => $forbiddenEx->getReason()], Codes::HTTP_FORBIDDEN);
            }
        }

        return $this->buildResponse($view, self::ACTION_DELETE, ['id' => $itemId, 'success' => $isProcessed]);
    }

    /**
     * @return CartApiEntityManager
     */
    protected function getCartManager()
    {
        return $this->get('orocrm_magento.cart.manager.api');
    }
}
