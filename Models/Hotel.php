<?php

namespace Models;

use JsonSerializable;

class Hotel implements JsonSerializable
{
    // public string $Id;
    
    // public ?string $Name;
    
    // public int $Stars;
    
    // // url
    // public ?string $WebAddress;
    
    // public ?HotelContent $Content;

    // public ?ContactPerson $ContactPerson;
    
    // public ?HotelAddress $Address;

    // public ?FacilityCollection $Facilities;

    /**
     * @param HotelImage[] $images
     */
    public function __construct(
        private string $id, 
        private string $name, 
        private ?City $city = null,
        private ?int $stars = null,
        private ?string $description = null,
        private ?array $images = null,
        private ?string $latitude = null,
        private ?string $longitude = null
    ){}




        // if ($stars !== null) {
        //     $hotel->Stars = $stars;
        // }

        // $content = new HotelContent();
        // $content->Content = $description;

        // if ($images !== null) {
        //     $imagesNew = new HotelImageGalleryItemCollection();
        //     foreach ($images as $image) {
        //         if (strlen($image->RemoteUrl) > 512) {
        //             continue;
        //         }
        //         $imagesNew->add($image);
        //     }

        //     $ig = new HotelImageGallery();
        //     $ig->Items = $imagesNew;
        //     $content->ImageGallery = $ig;
        // }

        // $hotel->Content = $content;

        // $hotel->Facilities = $facilities;
        
        // $address = new HotelAddress();
        // $address->City = $city;

        // if ($addressDetails !== null) {
        //     if (strlen($addressDetails) > 255) {
        //         $addressDetails = preg_replace('/\s+/', ' ', $addressDetails);
        //         if (strlen($addressDetails) > 255) {
        //             $addressDetails = substr($addressDetails, 0, 254);
        //         }
        //     }

        //     $address->Details = $addressDetails;
        // }


        // $address->Latitude = $latitude;
        // $address->Longitude = $longitude;

        // $hotel->Address = $address;

        // $contact = new ContactPerson();
        // $contact->Phone = $phone;
        // $contact->Fax = $fax;
        // $contact->Email = $email;

        // $hotel->ContactPerson = $contact;
        // $hotel->WebAddress = $webAddress;

    public function jsonSerialize(): array
    {
        $array = [
            'Id' => $this->id,
            'Name' => $this->name

            // 'Country' => $this->country->jsonSerialize(),
            // 'County' => $this->region->jsonSerialize()
        ];
        if (!empty($this->city)) {
            $array['City'] = $this->city;
        }
        if (!empty($this->stars)) {
            $array['Stars'] = $this->stars;
        }
        if (!empty($this->description)) {
            $array['Content']['Content'] = $this->description;
        }
        if (!empty($this->images)) {
            $array['Content']['ImageGallery']['Items'] = $this->description;
        }

       return $array;
    }
}