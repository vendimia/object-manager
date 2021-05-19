<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Vendimia\ObjectManager\ObjectManager;

require __DIR__ . '/../src/ObjectManager.php';
require __DIR__ . '/../src/AttributeParameterAbstract.php';
require __DIR__ . '/TestClassDefinitions.php';

final class ObjectManagerTest extends TestCase
{
    public function testCreateManager(): ObjectManager
    {
        $manager = new ObjectManager;

        $this->assertInstanceOf(ObjectManager::class, $manager);

        return $manager;
    }

    /** 
     * @depends testCreateManager
     */
    public function testInstantiateNewSimpleObject(ObjectManager $object): object
    {
        $new = $object->new(Simple::class);

        $this->assertInstanceOf(Simple::class, $new);

        return $new;
    }

    /** 
     * @depends testCreateManager
     */
    public function testInstantiateAndSaveSimpleObject(ObjectManager $object): object
    {
        $new = $object->get(Simple::class);

        $this->assertInstanceOf(Simple::class, $new);

        return $new;
    }

    /** 
     * @depends testInstantiateNewSimpleObject
     * @depends testInstantiateAndSaveSimpleObject
     */
    public function testNewAndGetShouldNotReturnSameObject(
        $new, 
        $get
    )
    {
        $this->assertNotSame($new, $get);
    }

    /**
     * @depends testCreateManager
     * @depends testInstantiateAndSaveSimpleObject
     */
    public function testGetShouldReturnPreviousSavedObject(ObjectManager $object, $saved)
    {
        $new = $object->get(Simple::class);

        $this->assertInstanceOf(Simple::class, $new);
        $this->assertSame($saved, $new);
    }

    /**
     * @depends testCreateManager
     */
    public function testSaveAnExistingObject(ObjectManager $object): object
    {
        $new = $object->save(new Complex);
        $this->assertInstanceOf(Complex::class, $new);

        return $new;
    }

    /**
     * @depends testCreateManager
     * @depends testSaveAnExistingObject
     */
    public function testRetrieveSavedObject(ObjectManager $object, $previous)
    {
        $saved = $object->get(Complex::class);
        $this->assertSame($previous, $saved);
    }

    /**
     * @depends testCreateManager
     */
    public function testInjectWithoutNeededBindings(ObjectManager $object)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PersonInterface');
        $car = $object->new(Car::class);
    }


    /**
     * @depends testCreateManager
     */
    public function testInjectWithBindings(ObjectManager $object)
    {
        $object->bind(PersonInterface::class, Bob::class);
        $object->bind(CarInterface::class, Mazda::class);

        $car = $object->new(CarInterface::class);

        $this->assertInstanceOf(Mazda::class, $car);
        $this->assertInstanceOf(Bob::class, $car->getChauffeur());
    }

    /**
     * @depends testCreateManager
     */
    public function testInjectAttributeParameterInConstructor(
        ObjectManager $object
    ): object
    {
        $wakawaka = $object->new(WakaWaka::class);

        $this->assertEquals('EhEh', $wakawaka->get());

        return $wakawaka;
    }

    /**
     * @depends testCreateManager
     * @depends testInjectAttributeParameterInConstructor
     */
    public function testInjectAttributeParameterInMethod(
        ObjectManager $object,
        $TsaminaMina
    )
    {
        $anaguah_ah_ah = $object->callMethod($TsaminaMina, 'zangalewa');

        $this->assertEquals('EhEh', $anaguah_ah_ah);
    }


    /**
     * @depends testCreateManager
     */
    public function testInjectAttributeParameterInFunction(
        ObjectManager $object
    )
    {
        $ChickenDinner = $object->call(function(#[Double] $Winner){
            return $Winner;
        });

        $this->assertEquals('WinnerWinner', $ChickenDinner);
    }

    /**
     * @depends testCreateManager
     */
    public function testUseInjectionWithArgumentsInFunction(
        ObjectManager $object
    )
    {
        $sum = $object->call(function(CarInterface $car, $first, $second) {

            // En testInjectWithBindings, CarInterface está unido a Mazda
            $this->assertInstanceOf(Mazda::class, $car);

            return $first + $second;
        }, first: 10,  second: 20);

        $this->assertEquals(30, $sum);
    }
}