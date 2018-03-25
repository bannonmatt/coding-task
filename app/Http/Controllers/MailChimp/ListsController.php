<?php
declare(strict_types=1);

namespace App\Http\Controllers\MailChimp;

use App\Database\Entities\MailChimp\MailChimpList;
use App\Database\Entities\MailChimp\MailChimpListMember;
use App\Http\Controllers\Controller;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mailchimp\Mailchimp;

class ListsController extends Controller
{
    /**
     * @var \Mailchimp\Mailchimp
     */
    private $mailChimp;

    /**
     * ListsController constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @param \Mailchimp\Mailchimp $mailchimp
     */
    public function __construct(EntityManagerInterface $entityManager, Mailchimp $mailchimp)
    {
        parent::__construct($entityManager);

        $this->mailChimp = $mailchimp;
    }

    /**
     * Create MailChimp list.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        // Instantiate entity
        $list = new MailChimpList($request->all());
        // Validate entity
        $validator = $this->getValidationFactory()->make($list->toMailChimpArray(), $list->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Save list into db
            $this->saveEntity($list);
            // Save list into MailChimp
            $response = $this->mailChimp->post('lists', $list->toMailChimpArray());
            // Set MailChimp id on the list and save list into db
            $this->saveEntity($list->setMailChimpId($response->get('id')));
        } catch (Exception $exception) {
            // Return error response if something goes wrong
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($list->toArray());
    }

    /**
     * Create MailChimp list member.
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createMember(Request $request, string $listId): JsonResponse
    {
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if (null === $list) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        // Instantiate entity
        $member_data = $request->all();
        $member = new MailChimpListMember($member_data);

        // Validate entity. Could this be passed to a generic validator?
        // eg. $validator = ValidationFactory::create($member);
        $validator = $this->getValidationFactory()->make($member_data, $member->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Save list into db
            $list->addMember($member);
            $this->saveEntity($member);
            $this->saveEntity($list);

            // Save member into MailChimp
            $response = $this->mailChimp->post(\sprintf('lists/%s/members', $list->getMailChimpId()), $member->toMailChimpArray());

            // Set MailChimp id on the list and save list into db
            $this->saveEntity($list->setMailChimpId($response->get('id')));
        } catch (Exception $exception) {
            // Return error response if something goes wrong
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($list->toArray());
    }

    /**
     * Remove MailChimp list.
     *
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove(string $listId): JsonResponse
    {
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if (null === $list) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        try {
            // Remove list from database
            $this->removeEntity($list);
            // Remove list from MailChimp
            $this->mailChimp->delete(\sprintf('lists/%s', $list->getMailChimpId()));
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse([]);
    }

    /**
     * Remove MailChimp member from a list.
     *
     * @param string $listId
     * @param string $memberId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(string $listId, string $memberId): JsonResponse
    {
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if (null === $list) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        $member = $this->entityManager->getRepository(MailChimpListMember::class)->find($memberId);
        if (null === $member) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpListMember[%s] not found', $memberId)],
                404
            );
        }

        try {
            // Remove member from database
            $this->removeEntity($member);
            $list->removeMember($member);

            // Remove member from MailChimp
            $this->mailChimp->delete(\sprintf('lists/%s/members/%s', $list->getMailChimpId(), $member->getMailChimpHash()));
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse([]);
    }

    /**
     * Retrieve and return MailChimp list.
     *
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $listId): JsonResponse
    {
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if (null === $list) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        return $this->successfulResponse($list->toArray());
    }

    /**
     * Show list members
     *
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function showMembers(string $listId): JsonResponse
    {
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if (null === $list) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        return $this->successfulResponse($list->getMembersAsResponse());
    }

    /**
     * Update MailChimp list.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $listId): JsonResponse
    {
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if (null === $list) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        // Update list properties
        $list->fill($request->all());
        // Validate entity
        $validator = $this->getValidationFactory()->make($list->toMailChimpArray(), $list->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Update list into database
            $this->saveEntity($list);
            // Update list into MailChimp
            $this->mailChimp->patch(\sprintf('lists/%s', $list->getMailChimpId()), $list->toMailChimpArray());
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($list->toArray());
    }

    /**
     * Update MailChimp list member.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $listId
     * @param string $memberId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMember(Request $request, string $listId, string $memberId): JsonResponse
    {
        $list = $this->entityManager->getRepository(MailChimpList::class)->find($listId);

        if (null === $list) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpList[%s] not found', $listId)],
                404
            );
        }

        $member = $this->entityManager->getRepository(MailChimpListMember::class)->find($memberId);

        if (null === $member) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpListMember[%s] not found', $memberId)],
                404
            );
        }

        // Cache the original email address, just incase it gets updated.
        // This is needed to identify the user when we hit the MC API.
        $original_email = $member->getEmailAddress();

        // Update member properties
        $updated_data = $request->all();
        $member->fill($updated_data);

        // Validate entity
        $validator = $this->getValidationFactory()->make($updated_data, $member->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Update member into database
            $this->saveEntity($member);

            // Update member into MailChimp
            $this->mailChimp->patch(\sprintf('lists/%s/members/%s', $list->getMailChimpId(), $member->getMailChimpHash($original_email)), $member->toMailChimpArray());
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($list->toArray());
    }

}
