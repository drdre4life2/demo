<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @method        Category|Proxy                                     create(array|callable $attributes = [])
 * @method static Category|Proxy                                     createOne(array $attributes = [])
 * @method static Category|Proxy                                     find(object|array|mixed $criteria)
 * @method static Category|Proxy                                     findOrCreate(array $attributes)
 * @method static Category|Proxy                                     first(string $sortedField = 'id')
 * @method static Category|Proxy                                     last(string $sortedField = 'id')
 * @method static Category|Proxy                                     random(array $attributes = [])
 * @method static Category|Proxy                                     randomOrCreate(array $attributes = [])
 * @method static Category[]|Proxy[]                                 all()
 * @method static Category[]|Proxy[]                                 createMany(int $number, array|callable $attributes = [])
 * @method static Category[]|Proxy[]                                 createSequence(iterable|callable $sequence)
 * @method static Category[]|Proxy[]                                 findBy(array $attributes)
 * @method static Category[]|Proxy[]                                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Category[]|Proxy[]                                 randomSet(int $number, array $attributes = [])
 * @method        FactoryCollection<Category|Proxy>                  many(int $min, int|null $max = null)
 * @method        FactoryCollection<Category|Proxy>                  sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<Category, CategoryRepository> repository()
 *
 * @phpstan-method Category&Proxy<Category> create(array|callable $attributes = [])
 * @phpstan-method static Category&Proxy<Category> createOne(array $attributes = [])
 * @phpstan-method static Category&Proxy<Category> find(object|array|mixed $criteria)
 * @phpstan-method static Category&Proxy<Category> findOrCreate(array $attributes)
 * @phpstan-method static Category&Proxy<Category> first(string $sortedField = 'id')
 * @phpstan-method static Category&Proxy<Category> last(string $sortedField = 'id')
 * @phpstan-method static Category&Proxy<Category> random(array $attributes = [])
 * @phpstan-method static Category&Proxy<Category> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<Category&Proxy<Category>> all()
 * @phpstan-method static list<Category&Proxy<Category>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Category&Proxy<Category>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Category&Proxy<Category>> findBy(array $attributes)
 * @phpstan-method static list<Category&Proxy<Category>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Category&Proxy<Category>> randomSet(int $number, array $attributes = [])
 * @phpstan-method FactoryCollection<Category&Proxy<Category>> many(int $min, int|null $max = null)
 * @phpstan-method FactoryCollection<Category&Proxy<Category>> sequence(iterable|callable $sequence)
 *
 * @extends PersistentProxyObjectFactory<Category>
 */
final class CategoryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
            'slug' => self::faker()->unique()->slug(),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Category::class;
    }
}
