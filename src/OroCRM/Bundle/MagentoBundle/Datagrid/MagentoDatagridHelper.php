<?php

namespace OroCRM\Bundle\MagentoBundle\Datagrid;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;

use OroCRM\Bundle\ChannelBundle\Entity\Channel;
use OroCRM\Bundle\MagentoBundle\Entity\CustomerGroup;
use OroCRM\Bundle\MagentoBundle\Provider\ChannelType;

class MagentoDatagridHelper
{
    /** @var EntityManager */
    protected $em;

    /** @var AclHelper */
    protected $aclHelper;

    /**
     * @param EntityManager $em
     * @param AclHelper     $aclHelper
     */
    public function __construct(EntityManager $em, AclHelper $aclHelper)
    {
        $this->em = $em;
        $this->aclHelper = $aclHelper;
    }

    /**
     * Returns query builder callback for magento channels
     *
     * @return callable
     */
    public function getMagentoChannelsQueryBuilder()
    {
        return function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
                ->where('c.type = :type')
                ->setParameter('type', ChannelType::TYPE);
        };
    }

    /**
     * Returns choices for Magento Customer Group filter
     * Labels include magento channel name if multiple magento channels are available
     *
     * @return array
     */
    public function getMagentoGroupsChoices()
    {
        $channelRepo = $this->em->getRepository(Channel::class);
        $groupRepo = $this->em->getRepository(CustomerGroup::class);
        $qb = $channelRepo->createQueryBuilder('mc')
            ->select('IDENTITY(mc.dataSource) as channelId, mc.name as name')
            ->where('mc.channelType = :type')
            ->setParameter('type', 'magento');
        $results = $this->aclHelper->apply($qb)->getArrayResult();
        $magentoChannels = array_combine(array_column($results, 'channelId'), array_column($results, 'name'));

        $addChannelName = count($magentoChannels) > 1;

        $qb = $groupRepo->createQueryBuilder('g')
            ->select('g.id as id, g.name as name, IDENTITY(g.channel) as channelId');
        $groups = $this->aclHelper->apply($qb)->getArrayResult();

        $choices = [];
        foreach ($groups as $group) {
            $groupName = $group['name'];
            if ($addChannelName && !empty($group['channelId']) && isset($magentoChannels[$group['channelId']])) {
                // append channel name
                $groupName .= sprintf(' (%s)', $magentoChannels[$group['channelId']]);
            }
            $choices[$group['id']] = $groupName;
        }

        return $choices;
    }
}
