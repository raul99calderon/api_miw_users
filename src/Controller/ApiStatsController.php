<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Result;
use App\Entity\User;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use function in_array;

/**
 * Class ApiStatsController
 *
 * @package App\Controller
 *
 * @Route(
 *     path=ApiStatsController::RUTA_API,
 *     name="api_stats_"
 * )
 */
class ApiStatsController extends AbstractController
{
    public const RUTA_API = '/api/v1/stats';
    private const HEADER_CACHE_CONTROL = 'Cache-Control';
    private const HEADER_ETAG = 'ETag';
    const NUM_MATCHES = 'num_matches';
    const MAX_RESULT = 'max_result';
    const MIN_RESULT = 'min_result';
    const TOTAL_SCORE = 'total_score';
    const AVERAGE_SCORE = 'average_score';
    const ROLE_ADMIN = 'ROLE_ADMIN';
    const STATS = 'stats';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    /**
     * GET Action
     * Summary: Retrieves stats
     *
     * @param Request $request
     * @return Response
     * @Route(
     *     path=".{_format}",
     *     defaults={ "_format": "json" },
     *     requirements={
     *          "resultId": "\d+",
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
    public function getAction(Request $request): Response
    {
        $format = Utils::getFormat($request);

        $email = $this->getUser()->getUserIdentifier();

        /** @var User $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($user == null) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);    // 404
        }

        /** @var Result[] $results */
        $results = $this->entityManager
            ->getRepository(Result::class)
            ->findBy(['user' => $user]);

        if (empty($results)) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);    // 404
        }

        // Caching with ETag
        $etag = md5((string) json_encode($results));
        if ($etags = $request->getETags()) {
            if (in_array($etag, $etags) || in_array('*', $etags)) {
                return new Response(null, Response::HTTP_NOT_MODIFIED); // 304
            }
        }

        $numMatches = count($results);
        $maxResult = $results[0]->getResult();
        $minResult = $results[0]->getResult();
        $totalScore = 0;
        foreach ($results as $result) {
            /** @var Result $result */
            if($result->getResult() > $maxResult) {
                $maxResult = $result->getResult();
            }
            if($result->getResult() < $minResult) {
                $minResult = $result->getResult();
            }
            $totalScore+=$result->getResult();
        }

        $stats = [
            self::NUM_MATCHES => $numMatches,
            self::MAX_RESULT => $maxResult,
            self::MIN_RESULT => $minResult,
            self::TOTAL_SCORE => $totalScore,
            self::AVERAGE_SCORE => round(($totalScore / ($numMatches * 1.0)),2)
        ];

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ self::STATS => $stats ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'must-revalidate',
                self::HEADER_ETAG => $etag,
            ]
        );
    }
}