<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\Factory\BookFactory;
use App\DataFixtures\Factory\ReviewFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Book;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Tests\Api\Security\TokenGenerator;
use App\Tests\Api\Trait\SerializerTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ReviewTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;
    use SerializerTrait;

    private Client $client;

    protected function setup(): void
    {
        $this->client = self::createClient();
    }

    /**
     * Filters are disabled on /books/{bookId}/reviews.
     */
    #[Test]
    #[DataProvider(methodName: 'getUrls')]
    public function asAnonymousICanGetACollectionOfBookReviewsWithoutFilters(FactoryCollection $factory, callable|string $url, int $hydraTotalItems, int $totalHydraMember = 30): void
    {
        $factory->create();

        if (\is_callable($url)) {
            $url = $url();
        }

        $response = $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertEquals('<https://localhost/.well-known/mercure>; rel="mercure"', $response->getHeaders()['link'][1]);
        self::assertJsonContains([
            'hydra:totalItems' => $hydraTotalItems,
        ]);
        self::assertCount(min($hydraTotalItems, $totalHydraMember), $response->toArray()['hydra:member']);
        self::assertMatchesJsonSchema(file_get_contents(__DIR__ . '/schemas/Review/collection.json'));
    }

    public static function getUrls(): iterable
    {
        yield 'all book reviews' => [
            ReviewFactory::new()->sequence(static function () {
                $book = BookFactory::createOne(['title' => 'Hyperion']);
                foreach (range(1, 35) as $i) {
                    yield ['book' => $book];
                }
            }),
            static function (): string {
                /** @var Book[] $books */
                $books = BookFactory::findBy(['title' => 'Hyperion']);

                return '/books/' . $books[0]->getId() . '/reviews';
            },
            35,
        ];
        yield 'all book reviews using itemsPerPage' => [
            ReviewFactory::new()->sequence(static function () {
                $book = BookFactory::createOne(['title' => 'Hyperion']);
                foreach (range(1, 20) as $i) {
                    yield ['book' => $book];
                }
            }),
            static function (): string {
                /** @var Book[] $books */
                $books = BookFactory::findBy(['title' => 'Hyperion']);

                return '/books/' . $books[0]->getId() . '/reviews?itemsPerPage=10';
            },
            20,
            10,
        ];
        yield 'book reviews filtered by rating (filter is disabled for non-admin users)' => [
            ReviewFactory::new()->sequence(static function () {
                $book = BookFactory::createOne(['title' => 'Hyperion']);
                foreach (range(1, 100) as $i) {
                    // 33% of reviews are rated 5
                    yield ['book' => $book, 'rating' => $i % 3 ? 3 : 5];
                }
            }),
            static function (): string {
                /** @var Book[] $books */
                $books = BookFactory::findBy(['title' => 'Hyperion']);

                return '/books/' . $books[0]->getId() . '/reviews?rating=5';
            },
            100,
        ];
        yield 'book reviews filtered by user (filter is disabled for non-admin users)' => [
            ReviewFactory::new()->sequence(static function () {
                $book = BookFactory::createOne(['title' => 'Hyperion']);
                yield ['book' => $book, 'user' => UserFactory::createOne(['email' => 'user@example.com'])];
                foreach (range(1, 34) as $i) {
                    yield ['book' => $book, 'user' => UserFactory::createOne()];
                }
            }),
            static function (): string {
                /** @var Book[] $books */
                $books = BookFactory::findBy(['title' => 'Hyperion']);
                /** @var User[] $users */
                $users = UserFactory::findBy(['email' => 'user@example.com']);

                return '/books/' . $books[0]->getId() . '/reviews?user=/users/' . $users[0]->getId();
            },
            35,
        ];
    }

    #[Test]
    public function asAnonymousICannotAddAReviewOnABook(): void
    {
        $book = BookFactory::createOne();

        $this->client->request('POST', '/books/' . $book->getId() . '/reviews', [
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Full authentication is required to access this resource.',
        ]);
    }

    #[Test]
    #[DataProvider(methodName: 'getInvalidData')]
    public function asAUserICannotAddAReviewOnABookWithInvalidData(array $data, int $statusCode, array $expected): void
    {
        $book = BookFactory::createOne();

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => UserFactory::createOne()->email,
            'authorize' => true,
        ]);

        $this->client->request('POST', '/books/' . $book->getId() . '/reviews', [
            'auth_bearer' => $token,
            'json' => $data,
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame($statusCode);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains($expected);
    }

    public static function getInvalidData(): iterable
    {
        yield 'empty data' => [
            [],
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                '@type' => 'ConstraintViolation',
                'hydra:title' => 'An error occurred',
                'violations' => [
                    [
                        'propertyPath' => 'body',
                        'message' => 'This value should not be blank.',
                    ],
                    [
                        'propertyPath' => 'rating',
                        'message' => 'This value should not be null.',
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function asAUserICannotAddAReviewWithValidDataOnAnInvalidBook(): void
    {
        $book = BookFactory::createOne();
        ReviewFactory::createMany(5, ['book' => $book]);
        $user = UserFactory::createOne();

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => $user->email,
        ]);

        $this->client->request('POST', '/books/invalid/reviews', [
            'auth_bearer' => $token,
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Invalid uri variables.',
        ]);
    }

    /**
     * @group mercure
     */
    #[Test]
    public function asAUserICanAddAReviewOnABook(): void
    {
        $book = BookFactory::createOne();
        ReviewFactory::createMany(5, ['book' => $book]);
        $user = UserFactory::createOne();
        self::getMercureHub()->reset();

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => $user->email,
            'authorize' => true,
        ]);

        $response = $this->client->request('POST', '/books/' . $book->getId() . '/reviews', [
            'auth_bearer' => $token,
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertEquals('<https://localhost/.well-known/mercure>; rel="mercure"', $response->getHeaders(false)['link'][1]);
        self::assertJsonContains([
            'book' => '/books/' . $book->getId(),
            'user' => [
                '@id' => '/users/' . $user->getId(),
            ],
            'body' => 'Very good book!',
            'rating' => 5,
        ]);
        self::assertMatchesJsonSchema(file_get_contents(__DIR__ . '/schemas/Review/item.json'));
        // if I add a review on a book with reviews, it doesn't erase the existing reviews
        $reviews = self::getContainer()->get(ReviewRepository::class)->findBy(['book' => $book->_real()]);
        self::assertCount(6, $reviews);
        $id = preg_replace('/^.*\/(.+)$/', '$1', $response->toArray()['@id']);
        /** @var Review $review */
        $review = self::getContainer()->get(ReviewRepository::class)->find($id);
        self::assertCount(1, self::getMercureMessages());
        self::assertMercureUpdateMatchesJsonSchema(
            update: self::getMercureMessage(),
            topics: [
                'http://localhost/admin/reviews/' . $review->getId(),
                'http://localhost/admin/reviews',
                'http://localhost/books/' . $book->getId() . '/reviews/' . $review->getId(),
                'http://localhost/books/' . $book->getId() . '/reviews',
            ],
            jsonSchema: file_get_contents(__DIR__ . '/Admin/schemas/Review/item.json')
        );
    }

    #[Test]
    public function asAUserICannotAddADuplicateReviewOnABook(): void
    {
        $book = BookFactory::createOne();
        ReviewFactory::createMany(5, ['book' => $book]);
        $user = UserFactory::createOne();
        ReviewFactory::createOne(['book' => $book, 'user' => $user]);

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => $user->email,
            'authorize' => true,
        ]);

        $this->client->request('POST', '/books/' . $book->getId() . '/reviews', [
            'auth_bearer' => $token,
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'You have already reviewed this book.',
        ]);
    }

    #[Test]
    public function asAnonymousICannotGetAnInvalidReview(): void
    {
        $book = BookFactory::createOne();

        $this->client->request('GET', '/books/' . $book->getId() . '/reviews/invalid');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'This route does not aim to be called.',
        ]);
    }

    #[Test]
    public function asAnonymousICanGetABookReview(): void
    {
        $review = ReviewFactory::createOne();

        $this->client->request('GET', '/books/' . $review->book->getId() . '/reviews/' . $review->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'This route does not aim to be called.',
        ]);
    }

    #[Test]
    public function asAnonymousICannotUpdateABookReview(): void
    {
        $review = ReviewFactory::createOne();

        $this->client->request('PATCH', '/books/' . $review->book->getId() . '/reviews/' . $review->getId(), [
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Full authentication is required to access this resource.',
        ]);
    }

    #[Test]
    public function asAUserICannotUpdateABookReviewOfAnotherUser(): void
    {
        $review = ReviewFactory::createOne(['user' => UserFactory::createOne()]);

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => UserFactory::createOne()->email,
            'authorize' => false,
        ]);

        $this->client->request('PATCH', '/books/' . $review->book->getId() . '/reviews/' . $review->getId(), [
            'auth_bearer' => $token,
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    #[Test]
    public function asAUserICannotUpdateAnInvalidBookReview(): void
    {
        $book = BookFactory::createOne();

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => UserFactory::createOne()->email,
        ]);

        $this->client->request('PATCH', '/books/' . $book->getId() . '/reviews/invalid', [
            'auth_bearer' => $token,
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @group mercure
     */
    #[Test]
    public function asAUserICanUpdateMyBookReview(): void
    {
        $review = ReviewFactory::createOne();
        self::getMercureHub()->reset();

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => $review->user->email,
            'authorize' => true,
        ]);

        $response = $this->client->request('PATCH', '/books/' . $review->book->getId() . '/reviews/' . $review->getId(), [
            'auth_bearer' => $token,
            'json' => [
                'body' => 'Very good book!',
                'rating' => 5,
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertEquals('<https://localhost/.well-known/mercure>; rel="mercure"', $response->getHeaders(false)['link'][1]);
        self::assertJsonContains([
            'body' => 'Very good book!',
            'rating' => 5,
        ]);
        self::assertMatchesJsonSchema(file_get_contents(__DIR__ . '/schemas/Review/item.json'));
        self::assertCount(1, self::getMercureMessages());
        self::assertMercureUpdateMatchesJsonSchema(
            update: self::getMercureMessage(),
            topics: [
                'http://localhost/admin/reviews/' . $review->getId(),
                'http://localhost/admin/reviews',
                'http://localhost/books/' . $review->book->getId() . '/reviews/' . $review->getId(),
                'http://localhost/books/' . $review->book->getId() . '/reviews',
            ],
            jsonSchema: file_get_contents(__DIR__ . '/Admin/schemas/Review/item.json')
        );
    }

    #[Test]
    public function asAnonymousICannotDeleteABookReview(): void
    {
        $review = ReviewFactory::createOne();

        $this->client->request('DELETE', '/books/' . $review->book->getId() . '/reviews/' . $review->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Full authentication is required to access this resource.',
        ]);
    }

    #[Test]
    public function asAUserICannotDeleteABookReviewOfAnotherUser(): void
    {
        $review = ReviewFactory::createOne(['user' => UserFactory::createOne()]);

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => UserFactory::createOne()->email,
            'authorize' => false,
        ]);

        $this->client->request('DELETE', '/books/' . $review->book->getId() . '/reviews/' . $review->getId(), [
            'auth_bearer' => $token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        self::assertResponseHeaderSame('link', '<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",<http://localhost/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"');
        self::assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    #[Test]
    public function asAUserICannotDeleteAnInvalidBookReview(): void
    {
        $book = BookFactory::createOne();

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => UserFactory::createOne()->email,
        ]);

        $this->client->request('DELETE', '/books/' . $book->getId() . '/reviews/invalid', [
            'auth_bearer' => $token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @group mercure
     */
    #[Test]
    public function asAUserICanDeleteMyBookReview(): void
    {
        $review = ReviewFactory::createOne(['body' => 'Best book ever!']);
        self::getMercureHub()->reset();
        $id = $review->getId();
        $bookId = $review->book->getId();

        $token = self::getContainer()->get(TokenGenerator::class)->generateToken([
            'email' => $review->user->email,
            'authorize' => true,
        ]);

        $response = $this->client->request('DELETE', '/books/' . $bookId . '/reviews/' . $id, [
            'auth_bearer' => $token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertEmpty($response->getContent());
        ReviewFactory::assert()->notExists(['body' => 'Best book ever!']);
        self::assertCount(1, self::getMercureMessages());
        self::assertEquals(
            new Update(
                topics: [
                    'http://localhost/admin/reviews/' . $id,
                    'http://localhost/admin/reviews',
                    'http://localhost/books/' . $bookId . '/reviews/' . $id,
                    'http://localhost/books/' . $bookId . '/reviews',
                ],
                data: json_encode(['@id' => '/admin/reviews/' . $id, '@type' => 'https://schema.org/Review']),
            ),
            self::getMercureMessage()
        );
    }
}
