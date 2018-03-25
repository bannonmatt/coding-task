<?php
declare(strict_types=1);

namespace App\Database\Entities\MailChimp;

use Doctrine\ORM\Mapping as ORM;
use EoneoPay\Utils\Str;

/**
 * @ORM\Entity()
 * @ORM\Table(name="mail_chimp_list_members")
 */
class MailChimpListMember extends MailChimpEntity
{

    /**
     * @ORM\ManyToOne(targetEntity="MailChimpList", inversedBy="members")
     * @var MailChimpList
     */
    protected $mailChimpList;

    /**
     * @ORM\Id()
     * @ORM\Column(name="id", type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     * @var string
     */
    private $memberId;

    /**
     * @ORM\Column(name="email_address", type="string")
     * @var string
     */
    private $emailAddress;

    /**
     * @ORM\Column(name="first_name", type="string")
     * @var string
     */
    private $firstName;

    /**
     * @ORM\Column(name="last_name", type="string")
     * @var string
     */
    private $lastName;

    /**
     * @ORM\Column(name="address", type="string")
     * @var string
     */
    private $address;

    /**
     * @ORM\Column(name="phone_number", type="string")
     * @var string
     */
    private $phoneNumber;

    /**
     * @ORM\Column(name="status", type="string")
     * @var string
     */
    private $status;

    /**
     * Get id.
     *
     * @return null|string
     */
    public function getId(): ?string
    {
        return $this->memberId;
    }

    /**
     * Get validation rules for mailchimp entity.
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'first_name'    => 'required|string',
            'last_name'     => 'required|string',
            'address'       => 'nullable|string',
            'phone_number'  => 'nullable|string',
            'email_address' => 'required|email',
        ];
    }

    /**
     * Set email address.
     *
     * @param string $emailAddress
     *
     * @return MailChimpListMember
     */
    public function setEmailAddress(string $emailAddress): MailChimpListMember
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    /**
     * Set first name.
     *
     * @param string $firstName
     *
     * @return MailChimpListMember
     */
    public function setFirstName(string $firstName): MailChimpListMember
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * Set last name.
     *
     * @param string $lastName
     *
     * @return \App\Database\Entities\MailChimp\MailChimpListMember
     */
    public function setLastName(string $lastName): MailChimpListMember
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Set address.
     *
     * @param string $address
     *
     * @return MailChimpListMember
     */
    public function setAddress(string $address): MailChimpListMember
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Set phone number.
     *
     * @param string $phoneNumber
     *
     * @return MailChimpListMember
     */
    public function setPhoneNumber(string $phoneNumber): MailChimpListMember
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return MailChimpListMember
     */
    public function setStatus(string $status): MailChimpListMember
    {
        $this->status = $status;

        return $this;
    }

    public function setMailChimpList(MailChimpList $list): void
    {
        $this->mailChimpList = $list;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getMailChimpList()
    {
        return $this->mailChimpList;
    }

    public function getMailChimpHash($email = NULL): string
    {
        if(!$email) {
            $email = $this->emailAddress;
        }

        return md5(strtolower($email));
    }

    /**
     * Get array representation of entity.
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = [];
        $str = new Str();

        foreach (\get_object_vars($this) as $property => $value) {
            if($property === 'mail_chimp_list') {
                continue;
            }

            $array[$str->snake($property)] = $value;
        }

        return $array;
    }

    /**
     * Get mailchimp array representation of entity.
     *
     * @return array
     */
    public function toMailChimpArray(): array
    {
        $array = ['merge_fields' => []];
        $str   = new Str();

        $required_fields = ['emailAddress', 'status'];
        $ignored_fields = ['mailChimpList'];

        foreach (\get_object_vars($this) as $property => $value) {
            if(in_array($property, $required_fields)) {
                $array[$str->snake($property)] = $value;
            } elseif(in_array($property, $ignored_fields)) {
                continue;
            } else {
                $property = $this->mapMergeFieldProperty($property);
                $array['merge_fields'][$property] = $value;
            }
        }

        return $array;
    }

    /**
     * Map the fields used in our database with the ones used by MailChimp
     *
     * @param $property
     *
     * @return string
     */
    public function mapMergeFieldProperty($property): string
    {
        $fields = ['firstName' => 'FNAME', 'lastName' => 'LNAME'];

        if(array_key_exists($property, $fields)) {
            return $fields[$property];
        }

        return strtoupper($property);
    }
}
