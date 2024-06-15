<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Api;


use Doctrine\Common\Collections\Criteria;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Repository\VendorRepository;
use JMS\Serializer\SerializerBuilder;
use Psr\Http\Message\ResponseInterface;

#[Route(
    path: 'admin/catalog/api/vendor',
    name: '@catalog_api_vendor_',
)]
final class Vendor extends AbstractController
{

    #[Route(
        path: '/find',
        name: 'find',
        methods: ['GET'],
        comment: 'API: Поиск бренда или производителя'
    )]
    public function find(VendorRepository $vendorRepository): ResponseInterface
    {
        $serializer = SerializerBuilder::create()->build();
        $query = $this->request->getQueryParams()['q'] ?? '';

        $result = $vendorRepository->matching(
            (new Criteria())
                ->where(Criteria::expr()->contains('name', $query))
        );

        return $this->json($serializer->toArray($result->toArray()));
    }
}
