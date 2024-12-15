<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Service\Search;


use Manticoresearch\Client;
use Psr\Log\LoggerInterface;

final class ManticoreSearch implements SearchInterface
{


    private Client $client;
    private ?string $error = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->client = new Client(
            [
                'host' => 'manticore',
                'port' => 9308,
            ],
            $logger
        );
    }

    public function getResult(SearchQuery $searchQuery, int $offset, int $limit): SearchResult
    {
        $index = $this->client->index('catalog');
       // dd($index->search('aad')->get());
        return new SearchResult($searchQuery, products: new \ArrayIterator([]));
//        $index->create([
//            'title'=>['type'=>'text'],
//            'plot'=>['type'=>'text'],
//            '_year'=>['type'=>'integer'],
//            'rating'=>['type'=>'float']
//        ]);
//        $index->addDocuments([
//            ['id'=>2,'title'=>'Interstellar','plot'=>'A team of explorers travel through a wormhole in space in an attempt to ensure humanity\'s survival.','_year'=>2014,'rating'=>8.5],
//            ['id'=>3,'title'=>'Inception','plot'=>'A thief who steals corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O.','_year'=>2010,'rating'=>8.8],
//            ['id'=>4,'title'=>'1917 ','plot'=>' As a regiment assembles to wage war deep in enemy territory, two soldiers are assigned to race against time and deliver a message that will stop 1,600 men from walking straight into a deadly trap.','_year'=>2018,'rating'=>8.4],
//            ['id'=>5,'title'=>'Alien','plot'=>' After a space merchant vessel receives an unknown transmission as a distress call, one of the team\'s member is attacked by a mysterious life form and they soon realize that its life cycle has merely begun.','_year'=>1979,'rating'=>8.4]
//        ]);
//        $results = $index->search('space team')->get();
//
//        foreach($results as $doc) {
//            echo 'Document:'.$doc->getId()."\n";
//            foreach($doc->getData() as $field=>$value)
//            {
//                echo $field.": ".$value."\n";
//            }
//        }
//        dd($results);
    }

    public function setError(string $error = null): void
    {
        $this->error = $error;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
