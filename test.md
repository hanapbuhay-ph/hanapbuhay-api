λ php artisan test tests/Feature/Worker --no-coverage

   FAIL  Tests\Feature\Worker\SubmitVerificationTest
  ✓ it allows worker to submit all 4 documents                                                                                                                                                              2.17s
  ✓ it allows worker to submit 3 required documents without skill_certificate                                                                                                                               0.06s
  ✓ it returns 403 for non-worker (client role)                                                                                                                                                             0.05s
  ⨯ it returns 422 when verification is already pending                                                                                                                                                     0.06s
  ⨯ it returns 422 when verification is already approved                                                                                                                                                    0.05s
  ✓ it returns 422 when required file government_id is missing                                                                                                                                              0.04s
  ✓ it creates verification_documents rows in the database                                                                                                                                                  0.06s
  ✓ it sets worker_profiles verification_status to pending                                                                                                                                                  0.06s

   PASS  Tests\Feature\Worker\UpdateWorkerProfileTest
  ✓ it updates bio and availability_status successfully                                                                                                                                                     0.06s
  ✓ it syncs category_ids and verifies pivot table is updated                                                                                                                                               0.06s
  ✓ it returns 403 for non-worker (client role)                                                                                                                                                             0.04s
  ✓ it returns 422 for invalid availability_status value                                                                                                                                                    0.03s
  ✓ it returns 422 when category_ids contains a non-existent ID                                                                                                                                             0.04s

   PASS  Tests\Feature\Worker\VerificationStatusTest
  ✓ it returns 200 with verification status for a worker                                                                                                                                                    0.05s
  ✓ it returns 403 for non-worker (client role)                                                                                                                                                             0.04s
  ✓ it response includes documents array with correct type and status fields                                                                                                                                0.04s
  ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\Worker\SubmitVerificationTest > it returns 422 when verification is already pending
  Unable to find JSON:

[{
    "success": false,
    "message": "You already have a pending or approved verification."
}]

within response JSON:

[{
    "message": "You already have a pending or approved verification.",
    "exception": "Symfony\\Component\\HttpKernel\\Exception\\HttpException",
    "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Application.php",
    "line": 1447,
    "trace": [
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\helpers.php",
            "line": 67,
            "function": "abort",
            "class": "Illuminate\\Foundation\\Application",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\app\\Services\\Worker\\WorkerService.php",
            "line": 30,
            "function": "abort"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\app\\Http\\Controllers\\Worker\\VerificationController.php",
            "line": 19,
            "function": "submitVerification",
            "class": "App\\Services\\Worker\\WorkerService",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\ControllerDispatcher.php",
            "line": 46,
            "function": "submit",
            "class": "App\\Http\\Controllers\\Worker\\VerificationController",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Route.php",
            "line": 276,
            "function": "dispatch",
            "class": "Illuminate\\Routing\\ControllerDispatcher",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Route.php",
            "line": 216,
            "function": "runController",
            "class": "Illuminate\\Routing\\Route",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 822,
            "function": "run",
            "class": "Illuminate\\Routing\\Route",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 180,
            "function": "{closure:Illuminate\\Routing\\Router::runRouteWithinStack():821}",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\app\\Http\\Middleware\\EnsureWorker.php",
            "line": 24,
            "function": "{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "App\\Http\\Middleware\\EnsureWorker",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Middleware\\SubstituteBindings.php",
            "line": 52,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Routing\\Middleware\\SubstituteBindings",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Auth\\Middleware\\Authenticate.php",
            "line": 63,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Auth\\Middleware\\Authenticate",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 137,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 821,
            "function": "then",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 800,
            "function": "runRouteWithinStack",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 764,
            "function": "runRoute",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 753,
            "function": "dispatchToRoute",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Kernel.php",
            "line": 200,
            "function": "dispatch",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 180,
            "function": "{closure:Illuminate\\Foundation\\Http\\Kernel::dispatchToRouter():197}",
            "class": "Illuminate\\Foundation\\Http\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest.php",
            "line": 21,
            "function": "{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\ConvertEmptyStringsToNull.php",
            "line": 31,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\ConvertEmptyStringsToNull",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest.php",
            "line": 21,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\TrimStrings.php",
            "line": 51,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\TrimStrings",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\ValidatePostSize.php",
            "line": 27,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\ValidatePostSize",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance.php",
            "line": 110,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\HandleCors.php",
            "line": 74,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\HandleCors",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\TrustProxies.php",
            "line": 58,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\TrustProxies",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\InvokeDeferredCallbacks.php",
            "line": 22,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\InvokeDeferredCallbacks",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\ValidatePathEncoding.php",
            "line": 28,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\ValidatePathEncoding",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 137,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Kernel.php",
            "line": 175,
            "function": "then",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Kernel.php",
            "line": 144,
            "function": "sendRequestThroughRouter",
            "class": "Illuminate\\Foundation\\Http\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Testing\\Concerns\\MakesHttpRequests.php",
            "line": 638,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Testing\\Concerns\\MakesHttpRequests.php",
            "line": 397,
            "function": "call",
            "class": "Illuminate\\Foundation\\Testing\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\tests\\Feature\\Worker\\SubmitVerificationTest.php",
            "line": 78,
            "function": "post",
            "class": "Illuminate\\Foundation\\Testing\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Factories\\TestCaseMethodFactory.php",
            "line": 177,
            "function": "{closure:C:\\laragon\\www\\hanapbuhay-api\\tests\\Feature\\Worker\\SubmitVerificationTest.php:74}",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "function": "{closure:Pest\\Factories\\TestCaseMethodFactory::getClosure():167}",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Concerns\\Testable.php",
            "line": 514,
            "function": "call_user_func_array"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Support\\ExceptionTrace.php",
            "line": 26,
            "function": "{closure:Pest\\Concerns\\Testable::__callClosure():514}",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Concerns\\Testable.php",
            "line": 514,
            "function": "ensure",
            "class": "Pest\\Support\\ExceptionTrace",
            "type": "::"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Concerns\\Testable.php",
            "line": 336,
            "function": "__callClosure",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Factories\\TestCaseFactory.php(179) : eval()'d code",
            "line": 61,
            "function": "__runTest",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 1318,
            "function": "__pest_evaluable_it_returns_422_when_verification_is_already_pending",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 1355,
            "function": "invokeTestMethod",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 521,
            "function": "runTest",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestRunner\\TestRunner.php",
            "line": 99,
            "function": "runBare",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 361,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestRunner",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestSuite.php",
            "line": 374,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestSuite.php",
            "line": 374,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestSuite",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\TextUI\\TestRunner.php",
            "line": 64,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestSuite",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\TextUI\\Application.php",
            "line": 229,
            "function": "run",
            "class": "PHPUnit\\TextUI\\TestRunner",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Kernel.php",
            "line": 103,
            "function": "run",
            "class": "PHPUnit\\TextUI\\Application",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest",
            "line": 196,
            "function": "handle",
            "class": "Pest\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest",
            "line": 204,
            "function": "{closure:C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest:19}"
        }
    ]
}].


Failed asserting that an array has the subset Array &0 [
    'success' => false,
    'message' => 'You already have a pending or approved verification.',
].
--- Expected
+++ Actual
@@ @@
       'function' => '{closure:C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest:19}',
     ),
   ),
-  'success' => false,
 )

  at tests\Feature\Worker\SubmitVerificationTest.php:85
     81▕         'selfie_with_id'       => fakeImage(),
     82▕     ]);
     83▕
     84▕     $response->assertStatus(422)
  ➜  85▕         ->assertJson(['success' => false, 'message' => 'You already have a pending or approved verification.']);
     86▕ });
     87▕
     88▕ it('returns 422 when verification is already approved', function () {
     89▕     $user    = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);

  ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\Worker\SubmitVerificationTest > it returns 422 when verification is already approved
  Unable to find JSON:

[{
    "success": false,
    "message": "You already have a pending or approved verification."
}]

within response JSON:

[{
    "message": "You already have a pending or approved verification.",
    "exception": "Symfony\\Component\\HttpKernel\\Exception\\HttpException",
    "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Application.php",
    "line": 1447,
    "trace": [
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\helpers.php",
            "line": 67,
            "function": "abort",
            "class": "Illuminate\\Foundation\\Application",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\app\\Services\\Worker\\WorkerService.php",
            "line": 30,
            "function": "abort"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\app\\Http\\Controllers\\Worker\\VerificationController.php",
            "line": 19,
            "function": "submitVerification",
            "class": "App\\Services\\Worker\\WorkerService",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\ControllerDispatcher.php",
            "line": 46,
            "function": "submit",
            "class": "App\\Http\\Controllers\\Worker\\VerificationController",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Route.php",
            "line": 276,
            "function": "dispatch",
            "class": "Illuminate\\Routing\\ControllerDispatcher",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Route.php",
            "line": 216,
            "function": "runController",
            "class": "Illuminate\\Routing\\Route",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 822,
            "function": "run",
            "class": "Illuminate\\Routing\\Route",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 180,
            "function": "{closure:Illuminate\\Routing\\Router::runRouteWithinStack():821}",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\app\\Http\\Middleware\\EnsureWorker.php",
            "line": 24,
            "function": "{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "App\\Http\\Middleware\\EnsureWorker",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Middleware\\SubstituteBindings.php",
            "line": 52,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Routing\\Middleware\\SubstituteBindings",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Auth\\Middleware\\Authenticate.php",
            "line": 63,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Auth\\Middleware\\Authenticate",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 137,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 821,
            "function": "then",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 800,
            "function": "runRouteWithinStack",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 764,
            "function": "runRoute",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Routing\\Router.php",
            "line": 753,
            "function": "dispatchToRoute",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Kernel.php",
            "line": 200,
            "function": "dispatch",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 180,
            "function": "{closure:Illuminate\\Foundation\\Http\\Kernel::dispatchToRouter():197}",
            "class": "Illuminate\\Foundation\\Http\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest.php",
            "line": 21,
            "function": "{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\ConvertEmptyStringsToNull.php",
            "line": 31,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\ConvertEmptyStringsToNull",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest.php",
            "line": 21,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\TrimStrings.php",
            "line": 51,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\TrimStrings",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\ValidatePostSize.php",
            "line": 27,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\ValidatePostSize",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance.php",
            "line": 110,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\HandleCors.php",
            "line": 74,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\HandleCors",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\TrustProxies.php",
            "line": 58,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\TrustProxies",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Middleware\\InvokeDeferredCallbacks.php",
            "line": 22,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Middleware\\InvokeDeferredCallbacks",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Http\\Middleware\\ValidatePathEncoding.php",
            "line": 28,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "Illuminate\\Http\\Middleware\\ValidatePathEncoding",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Pipeline\\Pipeline.php",
            "line": 137,
            "function": "{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():194}:195}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Kernel.php",
            "line": 175,
            "function": "then",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Http\\Kernel.php",
            "line": 144,
            "function": "sendRequestThroughRouter",
            "class": "Illuminate\\Foundation\\Http\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Testing\\Concerns\\MakesHttpRequests.php",
            "line": 638,
            "function": "handle",
            "class": "Illuminate\\Foundation\\Http\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Testing\\Concerns\\MakesHttpRequests.php",
            "line": 397,
            "function": "call",
            "class": "Illuminate\\Foundation\\Testing\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\tests\\Feature\\Worker\\SubmitVerificationTest.php",
            "line": 92,
            "function": "post",
            "class": "Illuminate\\Foundation\\Testing\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Factories\\TestCaseMethodFactory.php",
            "line": 177,
            "function": "{closure:C:\\laragon\\www\\hanapbuhay-api\\tests\\Feature\\Worker\\SubmitVerificationTest.php:88}",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "function": "{closure:Pest\\Factories\\TestCaseMethodFactory::getClosure():167}",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Concerns\\Testable.php",
            "line": 514,
            "function": "call_user_func_array"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Support\\ExceptionTrace.php",
            "line": 26,
            "function": "{closure:Pest\\Concerns\\Testable::__callClosure():514}",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Concerns\\Testable.php",
            "line": 514,
            "function": "ensure",
            "class": "Pest\\Support\\ExceptionTrace",
            "type": "::"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Concerns\\Testable.php",
            "line": 336,
            "function": "__callClosure",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Factories\\TestCaseFactory.php(179) : eval()'d code",
            "line": 74,
            "function": "__runTest",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 1318,
            "function": "__pest_evaluable_it_returns_422_when_verification_is_already_approved",
            "class": "P\\Tests\\Feature\\Worker\\SubmitVerificationTest",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 1355,
            "function": "invokeTestMethod",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 521,
            "function": "runTest",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestRunner\\TestRunner.php",
            "line": 99,
            "function": "runBare",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestCase.php",
            "line": 361,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestRunner",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestSuite.php",
            "line": 374,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestCase",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\Framework\\TestSuite.php",
            "line": 374,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestSuite",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\TextUI\\TestRunner.php",
            "line": 64,
            "function": "run",
            "class": "PHPUnit\\Framework\\TestSuite",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\phpunit\\phpunit\\src\\TextUI\\Application.php",
            "line": 229,
            "function": "run",
            "class": "PHPUnit\\TextUI\\TestRunner",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\src\\Kernel.php",
            "line": 103,
            "function": "run",
            "class": "PHPUnit\\TextUI\\Application",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest",
            "line": 196,
            "function": "handle",
            "class": "Pest\\Kernel",
            "type": "->"
        },
        {
            "file": "C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest",
            "line": 204,
            "function": "{closure:C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest:19}"
        }
    ]
}].


Failed asserting that an array has the subset Array &0 [
    'success' => false,
    'message' => 'You already have a pending or approved verification.',
].
--- Expected
+++ Actual
@@ @@
       'function' => '{closure:C:\\laragon\\www\\hanapbuhay-api\\vendor\\pestphp\\pest\\bin\\pest:19}',
     ),
   ),
-  'success' => false,
 )

  at tests\Feature\Worker\SubmitVerificationTest.php:99
     95▕         'selfie_with_id'       => fakeImage(),
     96▕     ]);
     97▕
     98▕     $response->assertStatus(422)
  ➜  99▕         ->assertJson(['success' => false, 'message' => 'You already have a pending or approved verification.']);
    100▕ });
    101▕
    102▕ it('returns 422 when required file government_id is missing', function () {
    103▕     $worker = makeWorker();


  Tests:    2 failed, 14 passed (46 assertions)
  Duration: 3.01s

