<?php

namespace IntegrationSupport;

use App\Entities\AvailabilityDates\AvailabilityDatesCollection;
use App\Support\Collections\AbstractTypedCollection;
use App\Support\Collections\Custom\AvailabilityCollection;
use ReflectionClass;

class ResponseConverter
{
    public static function convertToCollection(array $array, string $className): AbstractTypedCollection
    {
        $collection = new $className();
        foreach ($array as $key => $arrayItem) {
            if (in_array($collection->getItemType(), ['string', 'int', 'bool'])) {
                $item = $arrayItem;
            } else {
                $item = self::convertToItemResponse($arrayItem, $collection->getItemType());
            }
            
            $collection->put($key, $item);
        }
        return $collection;
    }

    public static function convertToAvailabilityDatesCollection(array $array): AvailabilityDatesCollection
    {
        return self::convertToCollection($array, AvailabilityDatesCollection::class);
    }

    public static function convertToItemResponse(mixed $anyObject, string $classType): ?object
    {
        $object = null;
        if ($anyObject != null) {
        
            $object = new $classType();

            $reflect = new ReflectionClass($object);
            foreach ($reflect->getProperties() as $property) {
                $propertyName = $property->name;
                if (is_array($anyObject) && array_key_exists($propertyName, $anyObject)) {
                    $propertyClass = $property->getType()->getName();

                    if (in_array($propertyClass, ['string', 'int', 'bool', 'float', 'array'])) {
                        $object->$propertyName = $anyObject[$propertyName];
                    } else {
                        $classObj = new $propertyClass();
                        if ($classObj instanceof AbstractTypedCollection && $anyObject[$propertyName] !== null) {
                            $innerObj = self::convertToCollection($anyObject[$propertyName], $propertyClass);
                        } else {
                            $innerObj = self::convertToItemResponse($anyObject[$propertyName], $propertyClass);
                        }
                        $object->$propertyName = $innerObj;
                    }
                } else {
                    //$object->$propertyName = null;
                }
            }
        }

        // foreach ($objectArr as $property => $value) {
        //     if (is_array($value)) {
        //         $value = self::convertToCollection($value, $propertyClass);
        //     }
        //     $object->$property = $value;
        // }

        return $object;
    }
}