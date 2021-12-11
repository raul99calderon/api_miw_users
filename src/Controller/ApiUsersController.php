<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

use function in_array;

/**
 * Class ApiUsersController
 *
 * @package App\Controller
 *
 * @Route(
 *     path=ApiUsersController::RUTA_API,
 *     name="api_users_"
 * )
 */
class ApiUsersController extends AbstractController
{

    public const RUTA_API = '/api/v1/users';

    private const HEADER_CACHE_CONTROL = 'Cache-Control';
    private const HEADER_ETAG = 'ETag';
    private const HEADER_ALLOW = 'Allow';
    private const ROLE_ADMIN = 'ROLE_ADMIN';

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $em;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * CGET Action
     * Summary: Retrieves the collection of User resources.
     * Notes: Returns all users from the system that the user has access to.
     *
     * @param   Request $request
     * @return  Response
     * @Route(
     *     path=".{_format}/{sort?id}",
     *     defaults={ "_format": "json", "sort": "id" },
     *     requirements={
     *         "sort": "id|email|roles",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_GET },
     *     name="cget"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function cgetAction(Request $request): Response
    {
        $order = $request->get('sort');
        $users = $this->entityManager
            ->getRepository(User::class)
            ->findBy([], [ $order => 'ASC' ]);
        $format = Utils::getFormat($request);

        // No hay usuarios?
        // @codeCoverageIgnoreStart
        if (empty($users)) {
            return $this->errorMessage(Response::HTTP_NOT_FOUND, null, $format);    // 404
        }
        // @codeCoverageIgnoreEnd

        // Caching with ETag
        $etag = md5((string) json_encode($users));
        if ($etags = $request->getETags()) {
            if (in_array($etag, $etags) || in_array('*', $etags)) {
                return new Response(null, Response::HTTP_NOT_MODIFIED); // 304
            }
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ 'users' => array_map(fn ($u) =>  ['user' => $u], $users) ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'must-revalidate',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * GET Action
     * Summary: Retrieves a User resource based on a single ID.
     * Notes: Returns the user identified by &#x60;userId&#x60;.
     *
     * @param Request $request
     * @param  int $userId User id
     * @return Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "userId": "\d+",
     *          "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_GET },
     *     name="get"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function getAction(Request $request, int $userId): Response
    {
        /** @var User $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);
        $format = Utils::getFormat($request);

        if (null == $user) {
            return $this->errorMessage(Response::HTTP_NOT_FOUND, null, $format);    // 404
        }

        // Caching with ETag
        $etag = md5((string) json_encode($user));
        if ($etags = $request->getETags()) {
            if (in_array($etag, $etags) || in_array('*', $etags)) {
                return new Response(null, Response::HTTP_NOT_MODIFIED); // 304
            }
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ User::USER_ATTR => $user ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'must-revalidate',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * Summary: Provides the list of HTTP supported methods
     * Notes: Return a &#x60;Allow&#x60; header with a list of HTTP supported methods.
     *
     * @param  int $userId User id
     * @return Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "userId" = 0, "_format": "json" },
     *     requirements={
     *          "userId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_OPTIONS },
     *     name="options"
     * )
     */
    public function optionsAction(int $userId): Response
    {
        $methods = $userId
            ? [ Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE ]
            : [ Request::METHOD_GET, Request::METHOD_POST ];
        $methods[] = Request::METHOD_OPTIONS;

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            [
                self::HEADER_ALLOW => implode(', ', $methods),
                self::HEADER_CACHE_CONTROL => 'public, inmutable'
            ]
        );
    }

    /**
     * DELETE Action
     * Summary: Removes the User resource.
     * Notes: Deletes the user identified by &#x60;userId&#x60;.
     *
     * @param   Request $request
     * @param   int $userId User id
     * @return  Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "userId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_DELETE },
     *     name="delete"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function deleteAction(Request $request, int $userId): Response
    {
        $format = Utils::getFormat($request);
        // Puede borrar un usuario sólo si tiene ROLE_ADMIN
        if (!$this->isGranted(self::ROLE_ADMIN)) {
            return $this->errorMessage( // 403
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you don\'t have permission to access',
                $format
            );
        }

        /** @var User $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (null == $user) {   // 404 - Not Found
            return $this->errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return Utils::apiResponse(Response::HTTP_NO_CONTENT);
    }

    /**
     * POST action
     * Summary: Creates a User resource.
     *
     * @param Request $request request
     * @return Response
     * @Route(
     *     path=".{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_POST },
     *     name="post"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function postAction(Request $request): Response
    {
        $format = Utils::getFormat($request);
        // Puede crear un usuario sólo si tiene ROLE_ADMIN
        if (!$this->isGranted(self::ROLE_ADMIN)) {
            return $this->errorMessage( // 403
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you don\'t have permission to access',
                $format
            );
        }
        $body = $request->getContent();
        $postData = json_decode((string) $body, true);

        if (!isset($postData[User::EMAIL_ATTR], $postData[User::PASSWD_ATTR])) {
            // 422 - Unprocessable Entity -> Faltan datos
            return $this->errorMessage(Response::HTTP_UNPROCESSABLE_ENTITY, null, $format);
        }

        // hay datos -> procesarlos
        $user_exist = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy([ User::EMAIL_ATTR => $postData[User::EMAIL_ATTR] ]);

        if (null !== $user_exist) {    // 400 - Bad Request
            return $this->errorMessage(Response::HTTP_BAD_REQUEST, null, $format);
        }

        // 201 - Created
        $user = new User(
            strval($postData[User::EMAIL_ATTR]),
            strval($postData[User::PASSWD_ATTR])
        );
        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            strval($postData[User::PASSWD_ATTR])
        );
        $user->setPassword($hashedPassword);
        // roles
        if (isset($postData[User::ROLES_ATTR])) {
            $user->setRoles($postData[User::ROLES_ATTR]);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            [ User::USER_ATTR => $user ],
            $format,
            [
                'Location' => $request->getScheme() . '://' . $request->getHttpHost() .
                    self::RUTA_API . '/' . $user->getId(),
            ]
        );
    }

    /**
     * PUT action
     * Summary: Updates the User resource.
     * Notes: Updates the user identified by &#x60;userId&#x60;.
     *
     * @param   Request $request request
     * @param   int $userId User id
     * @return  Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "userId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_PUT },
     *     name="put"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function putAction(Request $request, int $userId): Response
    {
        $format = Utils::getFormat($request);
        // Puede editar otro usuario diferente sólo si tiene ROLE_ADMIN
        if (
            ($this->getUser()->getId() !== $userId)
            && !$this->isGranted(self::ROLE_ADMIN)
        ) {
            return $this->errorMessage( // 403
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you don\'t have permission to access',
                $format
            );
        }
        $body = (string) $request->getContent();
        $postData = json_decode($body, true);

        /** @var User $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (null == $user) {    // 404 - Not Found
            return $this->errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        // Optimistic Locking (strong validation)
        $etag = md5((string) json_encode($user));
        if ($request->headers->has('If-Match') && $etag != $request->headers->get('If-Match')) {
            return $this->errorMessage(
                Response::HTTP_PRECONDITION_FAILED,
                'PRECONDITION FAILED: one or more conditions given evaluated to false',
                $format
            ); // 412
        }

        if (isset($postData[User::EMAIL_ATTR])) {
            $user_exist = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy([ User::EMAIL_ATTR => $postData[User::EMAIL_ATTR] ]);

            if (null !== $user_exist) {    // 400 - Bad Request
                return $this->errorMessage(Response::HTTP_BAD_REQUEST, null, $format);
            }
            $user->setEmail($postData[User::EMAIL_ATTR]);
        }

        // password
        if (isset($postData[User::PASSWD_ATTR])) {
            // hash the password (based on the security.yaml config for the $user class)
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $postData[User::PASSWD_ATTR]
            );
            $user->setPassword($hashedPassword);
        }

        // roles
        if (isset($postData[User::ROLES_ATTR])) {
            if (
                in_array(self::ROLE_ADMIN, $postData[User::ROLES_ATTR], true)
                && !$this->isGranted(self::ROLE_ADMIN)
            ) {
                return $this->errorMessage( // 403
                    Response::HTTP_FORBIDDEN,
                    '`Forbidden`: you don\'t have permission to access',
                    $format
                );
            }
            $user->setRoles($postData[User::ROLES_ATTR]);
        }

        $this->entityManager->flush();

        return Utils::apiResponse(
            209,                        // 209 - Content Returned
            [ User::USER_ATTR => $user ],
            $format
        );
    }

    /**
     * Error Message Response
     * @param int $status
     * @param string|null $customMessage
     * @param string $format
     *
     * @return Response
     */
    private function errorMessage(int $status, ?string $customMessage, string $format): Response
    {
        $customMessage = new Message(
            $status,
            $customMessage ?? strtoupper(Response::$statusTexts[$status])
        );
        return Utils::apiResponse(
            $customMessage->getCode(),
            $customMessage,
            $format
        );
    }
}
