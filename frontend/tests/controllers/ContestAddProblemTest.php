<?php

/**
 * Description of AddProblemToContestTest
 *
 * @author joemmanuel
 */

class AddProblemToContestTest extends \OmegaUp\Test\ControllerTestCase {
    /**
     * Check in DB for problem added to contest
     *
     * @param array $problemData
     * @param array $contestData
     * @param \OmegaUp\Request $r
     */
    public static function assertProblemAddedToContest(
        $problemData,
        $contestData,
        $r
    ) {
        // Get problem and contest from DB
        $problem = \OmegaUp\DAO\Problems::getByAlias(
            $problemData['request']['problem_alias']
        );
        $contest = \OmegaUp\DAO\Contests::getByAlias(
            $contestData['request']['alias']
        );

        // Get problem-contest and verify it
        $problemset_problems = \OmegaUp\DAO\ProblemsetProblems::getByPK(
            $contest->problemset_id,
            $problem->problem_id
        );
        self::assertNotNull($problemset_problems);
        self::assertEquals($r['points'], $problemset_problems->points);
        self::assertEquals($r['order_in_contest'], $problemset_problems->order);
    }

    /**
     * Add a problem to contest with valid params
     */
    public function testAddProblemToContestPositive() {
        // Get a problem
        $problemData = \OmegaUp\Test\Factories\Problem::createProblem();

        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();

        // Build request
        $directorLogin = self::login($contestData['director']);
        $r = new \OmegaUp\Request([
            'auth_token' => $directorLogin->auth_token,
            'contest_alias' => $contestData['request']['alias'],
            'problem_alias' => $problemData['request']['problem_alias'],
            'points' => 100,
            'order_in_contest' => 1,
        ]);

        // Call API
        $response = \OmegaUp\Controllers\Contest::apiAddProblem($r);

        // Validate
        $this->assertEquals('ok', $response['status']);

        self::assertProblemAddedToContest($problemData, $contestData, $r);
    }

    /**
     * Add a problem to contest with invalid params
     *
     * @expectedException \OmegaUp\Exceptions\InvalidParameterException
     */
    public function testAddProblemToContestInvalidProblem() {
        // Get a problem
        $problemData = \OmegaUp\Test\Factories\Problem::createProblem();

        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();
        // Build request
        $directorLogin = self::login($contestData['director']);
        $r = new \OmegaUp\Request([
            'auth_token' => $directorLogin->auth_token,
            'contest_alias' => $contestData['request']['alias'],
            'problem_alias' => 'this problem doesnt exists',
            'points' => 100,
            'order_in_contest' => 1,
        ]);

        // Call API
        $response = \OmegaUp\Controllers\Contest::apiAddProblem($r);
    }

    /**
     * Add a problem to contest with invalid params
     *
     * @expectedException \OmegaUp\Exceptions\InvalidParameterException
     */
    public function testAddProblemToContestInvalidContest() {
        // Get a problem
        $problemData = \OmegaUp\Test\Factories\Problem::createProblem();

        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();

        // Create an empty request
        $directorLogin = self::login($contestData['director']);
        $r = new \OmegaUp\Request([
            'auth_token' => $directorLogin->auth_token,
            'contest_alias' => 'invalid problem',
            'problem_alias' => $problemData['request']['alias'],
            'points' => 100,
            'order_in_contest' => 1,
        ]);

        // Call API
        $response = \OmegaUp\Controllers\Contest::apiAddProblem($r);
    }

    /**
     * Add a problem to contest with unauthorized user
     *
     * @expectedException \OmegaUp\Exceptions\ForbiddenAccessException
     */
    public function testAddProblemToContestWithUnauthorizedUser() {
        // Get a problem
        $problemData = \OmegaUp\Test\Factories\Problem::createProblem();

        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();

        // Log in as another random user
        ['user' => $user, 'identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();

        // Build request
        $userLogin = self::login($identity);
        $r = new \OmegaUp\Request([
            'auth_token' => $userLogin->auth_token,
            'contest_alias' => $contestData['request']['alias'],
            'problem_alias' => $problemData['request']['alias'],
            'points' => 100,
            'order_in_contest' => 1,
        ]);

        // Call API
        $response = \OmegaUp\Controllers\Contest::apiAddProblem($r);
    }

    /**
     * Add too many problems to a contest.
     */
    public function testAddTooManyProblemsToContest() {
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();
        $login = self::login($contestData['director']);

        for ($i = 0; $i < MAX_PROBLEMS_IN_CONTEST; $i++) {
            $problemData = \OmegaUp\Test\Factories\Problem::createProblemWithAuthor(
                $contestData['director'],
                $login
            );

            $r = new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'contest_alias' => $contestData['contest']->alias,
                'problem_alias' => $problemData['request']['problem_alias'],
                'points' => 100,
                'order_in_contest' => $i + 1,
            ]);
            $response = \OmegaUp\Controllers\Contest::apiAddProblem($r);
            $this->assertEquals('ok', $response['status']);
            self::assertProblemAddedToContest($problemData, $contestData, $r);
        }

        // Try to insert one more problem than is allowed, and it should fail this time.
        $problemData = \OmegaUp\Test\Factories\Problem::createProblemWithAuthor(
            $contestData['director'],
            $login
        );
        try {
            $response = \OmegaUp\Controllers\Contest::apiAddProblem(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'contest_alias' => $contestData['contest']->alias,
                'problem_alias' => $problemData['request']['problem_alias'],
                'points' => 100,
                'order_in_contest' => MAX_PROBLEMS_IN_CONTEST + 1,
            ]));
            $this->fail('Should have failed adding the problem to the contest');
        } catch (\OmegaUp\Exceptions\ApiException $e) {
            $this->assertEquals(
                $e->getMessage(),
                'contestAddproblemTooManyProblems'
            );
        }
    }

    /**
     * Attempt to add banned problems to a contest.
     */
    public function testAddBannedProblemToContest() {
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();
        $problemData = \OmegaUp\Test\Factories\Problem::createProblem(new \OmegaUp\Test\Factories\ProblemParams([
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC,
            'author' => $contestData['director']
        ]));
        $problem = $problemData['problem'];

        // Ban the problem.
        $problem->visibility = \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC_BANNED;
        \OmegaUp\DAO\Problems::update($problem);

        $directorLogin = self::login($contestData['director']);
        try {
            \OmegaUp\Controllers\Contest::apiAddProblem(new \OmegaUp\Request([
                'auth_token' => $directorLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
                'problem_alias' => $problemData['request']['problem_alias'],
                'points' => 100,
                'order_in_contest' => 1,
            ]));
            $this->fail(
                'Banned problems should not be able to be added to a contest'
            );
        } catch (\OmegaUp\Exceptions\ForbiddenAccessException $e) {
            $this->assertEquals($e->getMessage(), 'problemIsBanned');
        }

        // Make it private. Now it should be possible to add it.
        $problem->visibility = \OmegaUp\Controllers\Problem::VISIBILITY_PRIVATE;
        \OmegaUp\DAO\Problems::update($problem);

        $r = new \OmegaUp\Request([
            'auth_token' => $directorLogin->auth_token,
            'contest_alias' => $contestData['request']['alias'],
            'problem_alias' => $problemData['request']['problem_alias'],
            'points' => 100,
            'order_in_contest' => 1,
        ]);
        $response = \OmegaUp\Controllers\Contest::apiAddProblem($r);
        $this->assertEquals('ok', $response['status']);
        self::assertProblemAddedToContest($problemData, $contestData, $r);
    }
}
