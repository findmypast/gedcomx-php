<?php

namespace Gedcomx\tests\Extensions\FamilySearch\Rs\Client\FamilyTree;

use Gedcomx\Extensions\FamilySearch\FamilySearchPlatform;
use Gedcomx\Extensions\FamilySearch\Feature;
use Gedcomx\Extensions\FamilySearch\Rs\Client\FamilySearchStateFactory;
use Gedcomx\Extensions\FamilySearch\Rs\Client\FamilyTree\FamilyTreeStateFactory;
use Gedcomx\Extensions\FamilySearch\Rs\Client\Rel;
use Gedcomx\Extensions\FamilySearch\Rs\Client\Util\ExperimentsFilter;
use Gedcomx\Rs\Client\GedcomxApplicationState;
use Gedcomx\Rs\Client\Util\HttpStatus;
use Gedcomx\Tests\ApiTestCase;
use Gedcomx\Tests\PersonBuilder;
use Gedcomx\Rs\Client\Options\QueryParameter;
use Gedcomx\Rs\Client\Util\GedcomxPersonSearchQueryBuilder;
use Guzzle\Http\Message\Header\HeaderInterface;

class FamilyTreePersonStateTest extends ApiTestCase
{
    /**
     * @link https://familysearch.org/developers/docs/api/tree/Read_Person_Merge_Analysis_usecase
     */
    public function testReadMergeAnalysis()
    {
        $factory = new FamilyTreeStateFactory();
        $collection = $this->collectionState($factory);

        $person = PersonBuilder::buildPerson('male');
        $stateOne = $collection->addPerson($person)->get();
        $stateTwo = $collection->addPerson($person)->get();

        $analysis = $stateOne->readMergeAnalysis($stateTwo);
        $this->assertEquals(
            HttpStatus::OK,
            $analysis->getResponse()->getStatusCode(),
            $this->buildFailMessage(__METHOD__, $analysis)
        );
        $this->assertNotEmpty($analysis->getAnalysis());

        $stateTwo->delete();
        $stateOne->delete();
    }

    /**
     * @link https://familysearch.org/developers/docs/api/tree/Read_Person_Merge_Constraint_(Can_Merge_Any_Order)_usecase
     */
    public function testReadPersonMergeConstraintAnyOrder()
    {
        $factory = new FamilyTreeStateFactory();
        $collection = $this->collectionState($factory);

        $person = PersonBuilder::buildPerson('male');
        $person1 = $collection->addPerson($person)->get();
        $person2 = $collection->addPerson($person)->get();

        $state = $person1->readMergeOptions($person2);
        $this->assertEquals(
            HttpStatus::OK,
            $state->getResponse()->getStatusCode(),
            $this->buildFailMessage(__METHOD__, $state)
        );
        $this->assertArrayHasKey(Rel::MERGE_MIRROR, $state->getLinks(), $this->buildFailMessage(__METHOD__, $state));
    }

    /**
     * @link https://familysearch.org/developers/docs/api/tree/Read_Person_Merge_Constraint_(Can_Merge_Other_Order_Only)_usecase
     */
    public function testReadPersonMergeConstraintOtherOrder()
    {
        $factory = new FamilyTreeStateFactory();
        $collection = $this->collectionState($factory);

        $male = PersonBuilder::buildPerson('male');
        $female = PersonBuilder::buildPerson('female');
        $person1 = $collection->addPerson($male)->get();
        $person2 = $collection->addPerson($female)->get();

        $state = $person1->readMergeOptions($person2);
        $this->assertEquals(
            HttpStatus::OK,
            $state->getResponse()->getStatusCode(),
            $this->buildFailMessage(__METHOD__, $state)
        );
        $this->assertFalse($state->isAllowed(), $this->buildFailMessage(__METHOD__, $state));

        $person1->delete();
        $person2->delete();
    }

    public function testReadPersonPossibleDuplicates()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $p = PersonBuilder::buildPerson(null);
        $person1 = $this->collectionState()->addPerson($p);
        $person2 = $this->collectionState()->addPerson($p)->get();
        sleep(30); // This is to ensure the matching system on the server has time to recognize the two new duplicates
        $state = $person2->readMatches();

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 200);
        $this->assertGreaterThan(0, count($state->getResults()->getEntries()));

        $person1->delete();
        $person2->delete();
    }

    public function testReadPersonRecordMatches()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $person = $this->createPerson()->get();
        $query = new QueryParameter(true, "collection", "https://familysearch.org/platform/collections/records");
        $state = $person->readMatches($query);

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 204);
        $this->assertNull($state->getResults());

        $person->delete();
    }

    public function testReadAllMatchStatusTypesPersonRecordMatches()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $p = PersonBuilder::buildPerson(null);
        $person1 = $this->collectionState()->addPerson($p);
        $person2 = $this->collectionState()->addPerson($p)->get();
        sleep(30); // This is to ensure the matching system on the server has time to recognize the two new duplicates
        $statuses = new QueryParameter(true, "status", array("pending", "accepted", "rejected"));
        $state = $person2->readMatches($statuses);

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 200);
        $this->assertGreaterThan(0, count($state->getResults()->getEntries()));

        $person1->delete();
        $person2->delete();
    }

    public function testReadHigherConfidencePersonAcceptedRecordMatches()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $p = PersonBuilder::buildPerson(null);
        $person1 = $this->collectionState()->addPerson($p);
        $person2 = $this->collectionState()->addPerson($p)->get();
        sleep(30); // This is to ensure the matching system on the server has time to recognize the two new duplicates
        $statuses = new QueryParameter(true, "status", "accepted");
        $confidence = new QueryParameter(true, "confidence", "4");
        $state = $person2->readMatches($statuses, $confidence);

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 200);
        $this->assertGreaterThan(0, count($state->getResults()->getEntries()));

        $person1->delete();
        $person2->delete();
    }

    public function testUpdateMatchStatusForPersonRecordMatches()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $collection = new QueryParameter(true, "collection", "https://familysearch.org/platform/collections/records");
        $p = PersonBuilder::buildPerson(null);
        $person1 = $this->collectionState()->addPerson($p);
        $person2 = $this->collectionState()->addPerson($p)->get();
        sleep(30); // This is to ensure the matching system on the server has time to recognize the two new duplicates
        $matches = $person2->readMatches();
        $accepted = new QueryParameter(true, "status", "accepted");

        $entries = $matches->getResults()->getEntries();
        $state = $matches->updateMatchStatus(array_shift($entries), $accepted, $collection);

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 204);

        $person1->delete();
        $person2->delete();
    }

    public function testReadMatchScoresForPersons()
    {
        $factory = new FamilyTreeStateFactory();
        $collection = $this->collectionState($factory);

        $query = new GedcomxPersonSearchQueryBuilder();
        $query->givenName("GedcomX")
            ->surname("User")
            ->Gender("Male")
            ->BirthDate("June 1800")
            ->BirthPlace("Provo, Utah, Utah, United States")
            ->DeathDate("July 14, 1900")
            ->DeathPlace("Provo, Utah, Utah, United States");
        $state = $collection->searchForPersonMatches($query);

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 200);
        $this->assertNotNull($state->getResults());
        $this->assertNotNull($state->getResults()->getEntries());
        $this->assertGreaterThan(0, count($state->getResults()->getEntries()));
        $entries = $state->getResults()->getEntries();
        $this->assertGreaterThan(0, array_shift($entries)->getScore());
    }

    public function testSearchForPersonMatches()
    {
        $factory = new FamilyTreeStateFactory();
        $collection = $this->collectionState($factory);

        $query = new GedcomxPersonSearchQueryBuilder();
        $query->fatherSurname("Heaton")
            ->spouseSurname("Cox")
            ->surname("Heaton")
            ->givenName("Israel")
            ->birthPlace("Orderville, UT")
            ->deathDate("29 August 1936")
            ->deathPlace("Kanab, Kane, UT")
            ->spouseGivenName("Charlotte")
            ->motherGivenName("Clarissa")
            ->motherSurname("Hoyt")
            ->gender("Male")
            ->birthDate("30 January 1880")
            ->fatherGivenName("Jonathan");
        $state = $collection->searchForPersonMatches($query);

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 200);
        $this->assertNotNull($state->getResults());
        $this->assertNotNull($state->getResults()->getEntries());
        $this->assertGreaterThan(0, count($state->getResults()->getEntries()));
    }

    public function testReadPersonNotAMatchDeclarations()
    {
        $factory = new FamilyTreeStateFactory();
        $collection = $this->collectionState($factory);

        $p = PersonBuilder::buildPerson(null);
        $person1 = $this->collectionState()->addPerson($p);
        $person2 = $this->collectionState()->addPerson($p)->get();
        sleep(30); // This is to ensure the matching system on the server has time to recognize the two new duplicates
        $matches = $person2->readMatches();
        $entries = $matches->getResults()->getEntries();
        $entry = array_shift($entries);
        $id = $entry->getId();
        $match = $collection->readPersonById($id);
        $person2->addNonMatchState($match);
        $state = $person2->readNonMatches();

        $this->assertNotNull($state->ifSuccessful());
        $this->assertEquals((int)$state->getResponse()->getStatusCode(), 200);

        $person1->delete();
        $person2->delete();
    }

    public function testReadPersonWithMultiplePendingModificationsActivated()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);
        /** @var Feature[] $features */
        $features = array();

        $request = $this->collectionState()->getClient()->createRequest("GET", "https://sandbox.familysearch.org/platform/pending-modifications");
        $request->addHeader("Accept", GedcomxApplicationState::JSON_APPLICATION_TYPE);
        // Get all the features that are pending
        $response = $request->send($request);

        // Get each pending feature
        $json = json_decode($response->getBody(true), true);
        $fsp = new FamilySearchPlatform($json);
        foreach ($fsp->getFeatures() as $feature) {
            $features[] = $feature;
        }

        // Add every pending feature to the tree's current client
        $this->collectionState()->getClient()->addFilter(new ExperimentsFilter(array_map(function (Feature $feature) {
            return $feature->getName();
        }, $features)));

        $state = $this->createPerson();

        // Ensure a response came back
        $this->assertNotNull($state);
        $check = array();
        /** @var HeaderInterface $header */
        foreach ($state->getRequest()->getHeaders() as $header) {
            if ($header->getName() == "X-FS-Feature-Tag") {
                $check[] = $header;
            }
        }

        /** @var string[] $requestedFeatures */
        $requestedFeatures = join(",", $check);
        // Ensure each requested feature was found in the request headers
        foreach ($features as $feature) {
            $this->assertTrue(strpos($requestedFeatures, $feature->getName()) !== false, $feature->getName() . " was not found in the requested features.");
        }

        $state->delete();
    }

    public function testReadPersonWithPendingModificationActivated()
    {
        // The default client from this factory is assumed to add a single pending feature (if it doesn't, this test will fail)
        $factory = new FamilySearchStateFactory();
        $state = $this->collectionState($factory);

        $this->assertNotNull($state);
        $check = array();
        /** @var HeaderInterface $header */
        foreach ($state->getRequest()->getHeaders() as $header) {
            if ($header->getName() == "X-FS-Feature-Tag") {
                $check[] = $header;
            }
        }

        /** @var string[] $requestedFeatures */
        $requestedFeatures = join(",", $check);
        $this->assertNotNull($requestedFeatures);
        $this->assertEquals(false, strpos($requestedFeatures, ","));
        $this->assertEquals(1, count($requestedFeatures));
    }

    public function testRedirectToPerson()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $person = $this->createPerson();
        $id = $person->getResponse()->getHeader("X-ENTITY-ID")->__toString();
        $uri = "https://sandbox.familysearch.org/platform/redirect?person=" . $id;
        $request = $this->collectionState()->getClient()->createRequest("GET", $uri);
        $request->addHeader("Header", "application/json");
        $response = $this->collectionState()->getClient()->send($request);

        $this->assertNotNull($response);
        $this->assertEquals(1, $response->getRedirectCount());
        $this->assertNotEquals($uri, $response->getEffectiveUrl());

        $person->delete();
    }

    public function testRedirectToPersonMemories()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $person = $this->createPerson();
        $id = $person->getResponse()->getHeader("X-ENTITY-ID")->__toString();
        $uri = "https://sandbox.familysearch.org/platform/redirect?context=memories&person=" . $id;
        $request = $this->collectionState()->getClient()->createRequest("GET", $uri);
        $request->addHeader("Header", "application/json");
        $response = $this->collectionState()->getClient()->send($request);

        $this->assertNotNull($response);
        $this->assertEquals(1, $response->getRedirectCount());
        $this->assertNotEquals($uri, $response->getEffectiveUrl());

        $person->delete();
    }

    public function testRedirectToSourceLinker()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $person = $this->createPerson()->get();

        $identifiers = $person->getPerson()->getIdentifiers();
        $uri = sprintf("https://sandbox.familysearch.org/platform/redirect?context=sourcelinker&person=%s&hintId=%s", $person->getPerson()->getId(), array_shift($identifiers)->getValue());
        $request = $this->collectionState()->getClient()->createRequest("GET", $uri);
        $request->addHeader("Header", "application/json");
        $response = $this->collectionState()->getClient()->send($request);

        $this->assertNotNull($response);
        $this->assertEquals(1, $response->getRedirectCount());
        $this->assertNotEquals($uri, $response->getEffectiveUrl());

        $person->delete();
    }

    public function testRedirectToUri()
    {
        $factory = new FamilyTreeStateFactory();
        $this->collectionState($factory);

        $uri = "https://sandbox.familysearch.org/platform/redirect?uri=https://familysearch.org/some/path?p1%3Dp1-value%26p2%3Dp2-value";
        $request = $this->collectionState()->getClient()->createRequest("GET", $uri);
        $request->addHeader("Header", "application/json");
        $response = $this->collectionState()->getClient()->send($request);

        $this->assertNotNull($response);
        $this->assertEquals(1, $response->getRedirectCount());
        $this->assertNotEquals($uri, $response->getEffectiveUrl());
    }
}