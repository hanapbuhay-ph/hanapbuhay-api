C:\laragon\www\hanapbuhay-api(develop -> origin)
λ vendor\bin\pest tests/Feature/Notification/NotificationTest.php

   FAIL  Tests\Feature\Notification\NotificationTest
  ⨯ it registers a new device token                                                                                                                                                                         3.14s
  ⨯ it registering the same token twice results in only one row                                                                                                                                             0.05s
  ✓ it returns 422 when fcm_token is missing                                                                                                                                                                0.04s
  ✓ it returns 422 when device_type is invalid                                                                                                                                                              0.05s
  ✓ it returns 401 when unauthenticated on register-device                                                                                                                                                  0.04s
  ✓ it worker accepting a booking notifies the client                                                                                                                                                       0.10s
  ✓ it worker declining a booking notifies the client                                                                                                                                                       0.06s
  ✓ it worker completing a booking notifies the client                                                                                                                                                      0.06s
  ✓ it client rating a booking notifies the rated worker                                                                                                                                                    0.07s
  ⨯ it sendPush does nothing when user has no registered tokens                                                                                                                                             0.07s
  ⨯ it sendPush calls FCM send once for a user with one token                                                                                                                                               0.05s
  ⨯ it sendPush deletes invalid token on MessagingException and does not throw                                                                                                                              0.07s
  ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\Notification\NotificationTest > it registers a new device token
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist in C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php:435
Stack trace:
#0 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(435): PDO->prepare('select * from `...')
#1 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(846): Illuminate\Database\Connection->{closure:Illuminate\Database\Connection::select():426}('select * from `...', Array)
#2 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(813): Illuminate\Database\Connection->runQueryCallback('select * from `...', Array, Object(Closure))
#3 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(426): Illuminate\Database\Connection->run('select * from `...', Array, Object(Closure))
#4 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3629): Illuminate\Database\Connection->select('select * from `...', Array, true, Array)
#5 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3613): Illuminate\Database\Query\Builder->runSelect()
#6 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(930): Illuminate\Database\Query\Builder->get(Array)
#7 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(912): Illuminate\Database\Eloquent\Builder->getModels(Array)
#8 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Concerns\BuildsQueries.php(366): Illuminate\Database\Eloquent\Builder->get(Array)
#9 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(734): Illuminate\Database\Eloquent\Builder->first()
#10 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(768): Illuminate\Database\Eloquent\Builder->firstOrCreate(Array, Array)
#11 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Support\Traits\ForwardsCalls.php(23): Illuminate\Database\Eloquent\Builder->updateOrCreate(Array, Array)
#12 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2871): Illuminate\Database\Eloquent\Model->forwardCallTo(Object(Illuminate\Database\Eloquent\Builder), 'updateOrCreate', Array)
#13 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2887): Illuminate\Database\Eloquent\Model->__call('updateOrCreate', Array)
#14 C:\laragon\www\hanapbuhay-api\app\Http\Controllers\Notification\NotificationController.php(14): Illuminate\Database\Eloquent\Model::__callStatic('updateOrCreate', Array)
#15 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\ControllerDispatcher.php(46): App\Http\Controllers\Notification\NotificationController->registerDevice(Object(App\Http\Requests\Notification\RegisterDeviceRequest))
#16 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch(Object(Illuminate\Routing\Route), Object(App\Http\Controllers\Notification\NotificationController), 'registerDevice')
#17 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(216): Illuminate\Routing\Route->runController()
#18 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(822): Illuminate\Routing\Route->run()
#19 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}(Object(Illuminate\Http\Request))
#20 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Middleware\SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#21 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle(Object(Illuminate\Http\Request), Object(Closure))
#22 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Auth\Middleware\Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#23 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle(Object(Illuminate\Http\Request), Object(Closure), 'sanctum') #24 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#25 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(821): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#26 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(800): Illuminate\Routing\Router->runRouteWithinStack(Object(Illuminate\Routing\Route), Object(Illuminate\Http\Request))
#27 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(764): Illuminate\Routing\Router->runRoute(Object(Illuminate\Http\Request), Object(Illuminate\Routing\Route))
#28 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(753): Illuminate\Routing\Router->dispatchToRoute(Object(Illuminate\Http\Request))
#29 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(200): Illuminate\Routing\Router->dispatch(Object(Illuminate\Http\Request))
#30 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}(Object(Illuminate\Http\Request))
#31 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#32 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#33 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle(Object(Illuminate\Http\Request), Object(Closure))
#34 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#35 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#36 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle(Object(Illuminate\Http\Request), Object(Closure))
#37 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#38 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle(Object(Illuminate\Http\Request), Object(Closure))
#39 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#40 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle(Object(Illuminate\Http\Request), Object(Closure))
#41 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\HandleCors.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#42 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle(Object(Illuminate\Http\Request), Object(Closure))
#43 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#44 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle(Object(Illuminate\Http\Request), Object(Closure))
#45 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#46 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle(Object(Illuminate\Http\Request), Object(Closure))
#47 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#48 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle(Object(Illuminate\Http\Request), Object(Closure))
#49 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#50 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(175): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#51 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter(Object(Illuminate\Http\Request))
#52 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle(Object(Illuminate\Http\Request))
#53 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call('POST', '/api/notificati...', Array, Array, Array, Array, '{"fcm_token":"t...')
#54 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json('POST', '/api/notificati...', Array, Array, 0)
#55 C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php(34): Illuminate\Foundation\Testing\TestCase->postJson('/api/notificati...', Array)
#56 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseMethodFactory.php(177): P\Tests\Feature\Notification\NotificationTest->{closure:C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php:30}()
#57 [internal function]: P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Factories\TestCaseMethodFactory::getClosure():167}()
#58 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): call_user_func_array(Object(Closure), Array)
#59 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Support\ExceptionTrace.php(26): P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Concerns\Testable::__callClosure():514}()
#60 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): Pest\Support\ExceptionTrace::ensure(Object(Closure))
#61 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(336): P\Tests\Feature\Notification\NotificationTest->__callClosure(Object(Closure), Array)
#62 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseFactory.php(179) : eval()'d code(22): P\Tests\Feature\Notification\NotificationTest->__runTest(Object(Closure))
#63 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1318): P\Tests\Feature\Notification\NotificationTest->__pest_evaluable_it_registers_a_new_device_token()
#64 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod('__pest_evaluabl...', Array)
#65 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestRunner\TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(361): PHPUnit\Framework\TestRunner->run(Object(P\Tests\Feature\Notification\NotificationTest))
#68 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\Application.php(229): PHPUnit\TextUI\TestRunner->run(Object(PHPUnit\TextUI\Configuration\Configuration), Object(PHPUnit\Runner\ResultCache\DefaultResultCache), Object(PHPUnit\Framework\TestSuite))
#71 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Kernel.php(103): PHPUnit\TextUI\Application->run(Array)
#72 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(196): Pest\Kernel->handle(Array, Array)
#73 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(204): {closure:C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest:19}()
#74 C:\laragon\www\hanapbuhay-api\vendor\bin\pest(119): include('C:\\laragon\\www\\...')
#75 {main}

Next Illuminate\Database\QueryException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: hanapbuhay_test, SQL: select * from `device_tokens` where (`user_id` = 1 and `fcm_token` = token-abc-123) limit 1) in C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php:857
Stack trace:
#0 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(813): Illuminate\Database\Connection->runQueryCallback('select * from `...', Array, Object(Closure))
#1 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(426): Illuminate\Database\Connection->run('select * from `...', Array, Object(Closure))
#2 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3629): Illuminate\Database\Connection->select('select * from `...', Array, true, Array)
#3 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3613): Illuminate\Database\Query\Builder->runSelect()
#4 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(930): Illuminate\Database\Query\Builder->get(Array)
#5 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(912): Illuminate\Database\Eloquent\Builder->getModels(Array)
#6 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Concerns\BuildsQueries.php(366): Illuminate\Database\Eloquent\Builder->get(Array)
#7 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(734): Illuminate\Database\Eloquent\Builder->first()
#8 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(768): Illuminate\Database\Eloquent\Builder->firstOrCreate(Array, Array)
#9 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Support\Traits\ForwardsCalls.php(23): Illuminate\Database\Eloquent\Builder->updateOrCreate(Array, Array)
#10 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2871): Illuminate\Database\Eloquent\Model->forwardCallTo(Object(Illuminate\Database\Eloquent\Builder), 'updateOrCreate', Array)
#11 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2887): Illuminate\Database\Eloquent\Model->__call('updateOrCreate', Array)
#12 C:\laragon\www\hanapbuhay-api\app\Http\Controllers\Notification\NotificationController.php(14): Illuminate\Database\Eloquent\Model::__callStatic('updateOrCreate', Array)
#13 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\ControllerDispatcher.php(46): App\Http\Controllers\Notification\NotificationController->registerDevice(Object(App\Http\Requests\Notification\RegisterDeviceRequest))
#14 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch(Object(Illuminate\Routing\Route), Object(App\Http\Controllers\Notification\NotificationController), 'registerDevice')
#15 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(216): Illuminate\Routing\Route->runController()
#16 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(822): Illuminate\Routing\Route->run()
#17 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}(Object(Illuminate\Http\Request))
#18 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Middleware\SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#19 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle(Object(Illuminate\Http\Request), Object(Closure))
#20 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Auth\Middleware\Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#21 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle(Object(Illuminate\Http\Request), Object(Closure), 'sanctum') #22 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#23 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(821): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#24 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(800): Illuminate\Routing\Router->runRouteWithinStack(Object(Illuminate\Routing\Route), Object(Illuminate\Http\Request))
#25 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(764): Illuminate\Routing\Router->runRoute(Object(Illuminate\Http\Request), Object(Illuminate\Routing\Route))
#26 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(753): Illuminate\Routing\Router->dispatchToRoute(Object(Illuminate\Http\Request))
#27 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(200): Illuminate\Routing\Router->dispatch(Object(Illuminate\Http\Request))
#28 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}(Object(Illuminate\Http\Request))
#29 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#30 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#31 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle(Object(Illuminate\Http\Request), Object(Closure))
#32 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#33 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#34 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle(Object(Illuminate\Http\Request), Object(Closure))
#35 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#36 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle(Object(Illuminate\Http\Request), Object(Closure))
#37 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#38 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle(Object(Illuminate\Http\Request), Object(Closure))
#39 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\HandleCors.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#40 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle(Object(Illuminate\Http\Request), Object(Closure))
#41 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#42 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle(Object(Illuminate\Http\Request), Object(Closure))
#43 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#44 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle(Object(Illuminate\Http\Request), Object(Closure))
#45 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#46 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle(Object(Illuminate\Http\Request), Object(Closure))
#47 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#48 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(175): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#49 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter(Object(Illuminate\Http\Request))
#50 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle(Object(Illuminate\Http\Request))
#51 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call('POST', '/api/notificati...', Array, Array, Array, Array, '{"fcm_token":"t...')
#52 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json('POST', '/api/notificati...', Array, Array, 0)
#53 C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php(34): Illuminate\Foundation\Testing\TestCase->postJson('/api/notificati...', Array)
#54 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseMethodFactory.php(177): P\Tests\Feature\Notification\NotificationTest->{closure:C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php:30}()
#55 [internal function]: P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Factories\TestCaseMethodFactory::getClosure():167}()
#56 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): call_user_func_array(Object(Closure), Array)
#57 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Support\ExceptionTrace.php(26): P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Concerns\Testable::__callClosure():514}()
#58 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): Pest\Support\ExceptionTrace::ensure(Object(Closure))
#59 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(336): P\Tests\Feature\Notification\NotificationTest->__callClosure(Object(Closure), Array)
#60 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseFactory.php(179) : eval()'d code(22): P\Tests\Feature\Notification\NotificationTest->__runTest(Object(Closure))
#61 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1318): P\Tests\Feature\Notification\NotificationTest->__pest_evaluable_it_registers_a_new_device_token()
#62 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod('__pest_evaluabl...', Array)
#63 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#64 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestRunner\TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#65 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(361): PHPUnit\Framework\TestRunner->run(Object(P\Tests\Feature\Notification\NotificationTest))
#66 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#67 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#68 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\Application.php(229): PHPUnit\TextUI\TestRunner->run(Object(PHPUnit\TextUI\Configuration\Configuration), Object(PHPUnit\Runner\ResultCache\DefaultResultCache), Object(PHPUnit\Framework\TestSuite))
#69 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Kernel.php(103): PHPUnit\TextUI\Application->run(Array)
#70 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(196): Pest\Kernel->handle(Array, Array)
#71 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(204): {closure:C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest:19}()
#72 C:\laragon\www\hanapbuhay-api\vendor\bin\pest(119): include('C:\\laragon\\www\\...')
#73 {main}

----------------------------------------------------------------------------------

SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: hanapbuhay_test, SQL: select * from `device_tokens` where (`user_id` = 1 and `fcm_token` = token-abc-123) limit 1)

  at tests\Feature\Notification\NotificationTest.php:38
     34▕         ->postJson('/api/notifications/register-device', [
     35▕             'fcm_token'   => 'token-abc-123',
     36▕             'device_type' => 'android',
     37▕         ])
  ➜  38▕         ->assertStatus(200)
     39▕         ->assertJsonPath('success', true)
     40▕         ->assertJsonPath('message', 'Device registered successfully.');
     41▕
     42▕     $this->assertDatabaseHas('device_tokens', [

  ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\Notification\NotificationTest > it registering the same token twice results in only one row
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist in C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php:435
Stack trace:
#0 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(435): PDO->prepare('select * from `...')
#1 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(846): Illuminate\Database\Connection->{closure:Illuminate\Database\Connection::select():426}('select * from `...', Array)
#2 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(813): Illuminate\Database\Connection->runQueryCallback('select * from `...', Array, Object(Closure))
#3 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(426): Illuminate\Database\Connection->run('select * from `...', Array, Object(Closure))
#4 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3629): Illuminate\Database\Connection->select('select * from `...', Array, true, Array)
#5 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3613): Illuminate\Database\Query\Builder->runSelect()
#6 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(930): Illuminate\Database\Query\Builder->get(Array)
#7 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(912): Illuminate\Database\Eloquent\Builder->getModels(Array)
#8 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Concerns\BuildsQueries.php(366): Illuminate\Database\Eloquent\Builder->get(Array)
#9 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(734): Illuminate\Database\Eloquent\Builder->first()
#10 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(768): Illuminate\Database\Eloquent\Builder->firstOrCreate(Array, Array)
#11 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Support\Traits\ForwardsCalls.php(23): Illuminate\Database\Eloquent\Builder->updateOrCreate(Array, Array)
#12 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2871): Illuminate\Database\Eloquent\Model->forwardCallTo(Object(Illuminate\Database\Eloquent\Builder), 'updateOrCreate', Array)
#13 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2887): Illuminate\Database\Eloquent\Model->__call('updateOrCreate', Array)
#14 C:\laragon\www\hanapbuhay-api\app\Http\Controllers\Notification\NotificationController.php(14): Illuminate\Database\Eloquent\Model::__callStatic('updateOrCreate', Array)
#15 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\ControllerDispatcher.php(46): App\Http\Controllers\Notification\NotificationController->registerDevice(Object(App\Http\Requests\Notification\RegisterDeviceRequest))
#16 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch(Object(Illuminate\Routing\Route), Object(App\Http\Controllers\Notification\NotificationController), 'registerDevice')
#17 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(216): Illuminate\Routing\Route->runController()
#18 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(822): Illuminate\Routing\Route->run()
#19 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}(Object(Illuminate\Http\Request))
#20 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Middleware\SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#21 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle(Object(Illuminate\Http\Request), Object(Closure))
#22 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Auth\Middleware\Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#23 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle(Object(Illuminate\Http\Request), Object(Closure), 'sanctum') #24 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#25 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(821): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#26 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(800): Illuminate\Routing\Router->runRouteWithinStack(Object(Illuminate\Routing\Route), Object(Illuminate\Http\Request))
#27 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(764): Illuminate\Routing\Router->runRoute(Object(Illuminate\Http\Request), Object(Illuminate\Routing\Route))
#28 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(753): Illuminate\Routing\Router->dispatchToRoute(Object(Illuminate\Http\Request))
#29 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(200): Illuminate\Routing\Router->dispatch(Object(Illuminate\Http\Request))
#30 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}(Object(Illuminate\Http\Request))
#31 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#32 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#33 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle(Object(Illuminate\Http\Request), Object(Closure))
#34 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#35 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#36 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle(Object(Illuminate\Http\Request), Object(Closure))
#37 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#38 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle(Object(Illuminate\Http\Request), Object(Closure))
#39 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#40 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle(Object(Illuminate\Http\Request), Object(Closure))
#41 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\HandleCors.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#42 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle(Object(Illuminate\Http\Request), Object(Closure))
#43 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#44 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle(Object(Illuminate\Http\Request), Object(Closure))
#45 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#46 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle(Object(Illuminate\Http\Request), Object(Closure))
#47 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#48 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle(Object(Illuminate\Http\Request), Object(Closure))
#49 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#50 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(175): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#51 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter(Object(Illuminate\Http\Request))
#52 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle(Object(Illuminate\Http\Request))
#53 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call('POST', '/api/notificati...', Array, Array, Array, Array, '{"fcm_token":"t...')
#54 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json('POST', '/api/notificati...', Array, Array, 0)
#55 C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php(53): Illuminate\Foundation\Testing\TestCase->postJson('/api/notificati...', Array)
#56 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseMethodFactory.php(177): P\Tests\Feature\Notification\NotificationTest->{closure:C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php:48}()
#57 [internal function]: P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Factories\TestCaseMethodFactory::getClosure():167}()
#58 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): call_user_func_array(Object(Closure), Array)
#59 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Support\ExceptionTrace.php(26): P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Concerns\Testable::__callClosure():514}()
#60 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): Pest\Support\ExceptionTrace::ensure(Object(Closure))
#61 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(336): P\Tests\Feature\Notification\NotificationTest->__callClosure(Object(Closure), Array)
#62 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseFactory.php(179) : eval()'d code(35): P\Tests\Feature\Notification\NotificationTest->__runTest(Object(Closure))
#63 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1318): P\Tests\Feature\Notification\NotificationTest->__pest_evaluable_it_registering_the_same_token_twice_results_in_only_one_row()
#64 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod('__pest_evaluabl...', Array)
#65 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestRunner\TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(361): PHPUnit\Framework\TestRunner->run(Object(P\Tests\Feature\Notification\NotificationTest))
#68 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\Application.php(229): PHPUnit\TextUI\TestRunner->run(Object(PHPUnit\TextUI\Configuration\Configuration), Object(PHPUnit\Runner\ResultCache\DefaultResultCache), Object(PHPUnit\Framework\TestSuite))
#71 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Kernel.php(103): PHPUnit\TextUI\Application->run(Array)
#72 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(196): Pest\Kernel->handle(Array, Array)
#73 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(204): {closure:C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest:19}()
#74 C:\laragon\www\hanapbuhay-api\vendor\bin\pest(119): include('C:\\laragon\\www\\...')
#75 {main}

Next Illuminate\Database\QueryException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: hanapbuhay_test, SQL: select * from `device_tokens` where (`user_id` = 2 and `fcm_token` = token-abc-123) limit 1) in C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php:857
Stack trace:
#0 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(813): Illuminate\Database\Connection->runQueryCallback('select * from `...', Array, Object(Closure))
#1 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Connection.php(426): Illuminate\Database\Connection->run('select * from `...', Array, Object(Closure))
#2 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3629): Illuminate\Database\Connection->select('select * from `...', Array, true, Array)
#3 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php(3613): Illuminate\Database\Query\Builder->runSelect()
#4 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(930): Illuminate\Database\Query\Builder->get(Array)
#5 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(912): Illuminate\Database\Eloquent\Builder->getModels(Array)
#6 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Concerns\BuildsQueries.php(366): Illuminate\Database\Eloquent\Builder->get(Array)
#7 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(734): Illuminate\Database\Eloquent\Builder->first()
#8 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php(768): Illuminate\Database\Eloquent\Builder->firstOrCreate(Array, Array)
#9 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Support\Traits\ForwardsCalls.php(23): Illuminate\Database\Eloquent\Builder->updateOrCreate(Array, Array)
#10 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2871): Illuminate\Database\Eloquent\Model->forwardCallTo(Object(Illuminate\Database\Eloquent\Builder), 'updateOrCreate', Array)
#11 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Database\Eloquent\Model.php(2887): Illuminate\Database\Eloquent\Model->__call('updateOrCreate', Array)
#12 C:\laragon\www\hanapbuhay-api\app\Http\Controllers\Notification\NotificationController.php(14): Illuminate\Database\Eloquent\Model::__callStatic('updateOrCreate', Array)
#13 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\ControllerDispatcher.php(46): App\Http\Controllers\Notification\NotificationController->registerDevice(Object(App\Http\Requests\Notification\RegisterDeviceRequest))
#14 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch(Object(Illuminate\Routing\Route), Object(App\Http\Controllers\Notification\NotificationController), 'registerDevice')
#15 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Route.php(216): Illuminate\Routing\Route->runController()
#16 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(822): Illuminate\Routing\Route->run()
#17 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}(Object(Illuminate\Http\Request))
#18 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Middleware\SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#19 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle(Object(Illuminate\Http\Request), Object(Closure))
#20 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Auth\Middleware\Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#21 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle(Object(Illuminate\Http\Request), Object(Closure), 'sanctum') #22 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#23 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(821): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#24 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(800): Illuminate\Routing\Router->runRouteWithinStack(Object(Illuminate\Routing\Route), Object(Illuminate\Http\Request))
#25 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(764): Illuminate\Routing\Router->runRoute(Object(Illuminate\Http\Request), Object(Illuminate\Routing\Route))
#26 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Routing\Router.php(753): Illuminate\Routing\Router->dispatchToRoute(Object(Illuminate\Http\Request))
#27 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(200): Illuminate\Routing\Router->dispatch(Object(Illuminate\Http\Request))
#28 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}(Object(Illuminate\Http\Request))
#29 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}(Object(Illuminate\Http\Request))
#30 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#31 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle(Object(Illuminate\Http\Request), Object(Closure))
#32 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#33 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle(Object(Illuminate\Http\Request), Object(Closure))
#34 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle(Object(Illuminate\Http\Request), Object(Closure))
#35 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#36 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle(Object(Illuminate\Http\Request), Object(Closure))
#37 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#38 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle(Object(Illuminate\Http\Request), Object(Closure))
#39 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\HandleCors.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#40 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle(Object(Illuminate\Http\Request), Object(Closure))
#41 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#42 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle(Object(Illuminate\Http\Request), Object(Closure))
#43 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#44 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle(Object(Illuminate\Http\Request), Object(Closure))
#45 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#46 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle(Object(Illuminate\Http\Request), Object(Closure))
#47 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}(Object(Illuminate\Http\Request))
#48 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(175): Illuminate\Pipeline\Pipeline->then(Object(Closure))
#49 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter(Object(Illuminate\Http\Request))
#50 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle(Object(Illuminate\Http\Request))
#51 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call('POST', '/api/notificati...', Array, Array, Array, Array, '{"fcm_token":"t...')
#52 C:\laragon\www\hanapbuhay-api\vendor\laravel\framework\src\Illuminate\Foundation\Testing\Concerns\MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json('POST', '/api/notificati...', Array, Array, 0)
#53 C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php(53): Illuminate\Foundation\Testing\TestCase->postJson('/api/notificati...', Array)
#54 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseMethodFactory.php(177): P\Tests\Feature\Notification\NotificationTest->{closure:C:\laragon\www\hanapbuhay-api\tests\Feature\Notification\NotificationTest.php:48}()
#55 [internal function]: P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Factories\TestCaseMethodFactory::getClosure():167}()
#56 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): call_user_func_array(Object(Closure), Array)
#57 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Support\ExceptionTrace.php(26): P\Tests\Feature\Notification\NotificationTest->{closure:Pest\Concerns\Testable::__callClosure():514}()
#58 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(514): Pest\Support\ExceptionTrace::ensure(Object(Closure))
#59 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Concerns\Testable.php(336): P\Tests\Feature\Notification\NotificationTest->__callClosure(Object(Closure), Array)
#60 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Factories\TestCaseFactory.php(179) : eval()'d code(35): P\Tests\Feature\Notification\NotificationTest->__runTest(Object(Closure))
#61 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1318): P\Tests\Feature\Notification\NotificationTest->__pest_evaluable_it_registering_the_same_token_twice_results_in_only_one_row()
#62 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod('__pest_evaluabl...', Array)
#63 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#64 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestRunner\TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#65 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestCase.php(361): PHPUnit\Framework\TestRunner->run(Object(P\Tests\Feature\Notification\NotificationTest))
#66 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\Framework\TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#67 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#68 C:\laragon\www\hanapbuhay-api\vendor\phpunit\phpunit\src\TextUI\Application.php(229): PHPUnit\TextUI\TestRunner->run(Object(PHPUnit\TextUI\Configuration\Configuration), Object(PHPUnit\Runner\ResultCache\DefaultResultCache), Object(PHPUnit\Framework\TestSuite))
#69 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\src\Kernel.php(103): PHPUnit\TextUI\Application->run(Array)
#70 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(196): Pest\Kernel->handle(Array, Array)
#71 C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest(204): {closure:C:\laragon\www\hanapbuhay-api\vendor\pestphp\pest\bin\pest:19}()
#72 C:\laragon\www\hanapbuhay-api\vendor\bin\pest(119): include('C:\\laragon\\www\\...')
#73 {main}

----------------------------------------------------------------------------------

SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: hanapbuhay_test, SQL: select * from `device_tokens` where (`user_id` = 2 and `fcm_token` = token-abc-123) limit 1)

  at tests\Feature\Notification\NotificationTest.php:53
     49▕     $user = makeBookingClient();
     50▕
     51▕     $payload = ['fcm_token' => 'token-abc-123', 'device_type' => 'android'];
     52▕
  ➜  53▕     $this->actingAs($user)->postJson('/api/notifications/register-device', $payload)->assertStatus(200);
     54▕     $this->actingAs($user)->postJson('/api/notifications/register-device', $payload)->assertStatus(200);
     55▕
     56▕     expect(DeviceToken::where('user_id', $user->id)->count())->toBe(1);
     57▕ });

  ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\Notification\NotificationTest > it sendPush does nothing when user has no registered tokens                                                                              QueryException
  SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: hanapbuhay_test, SQL: select * from `device_tokens` where `user_id` = 13)

  at vendor\laravel\framework\src\Illuminate\Database\Connection.php:435
    431▕             // For select statements, we'll simply execute the query and return an array
    432▕             // of the database result set. Each element in the array will be a single
    433▕             // row from the database table, and will either be an array or objects.
    434▕             $statement = $this->prepared(
  ➜ 435▕                 $this->getPdoForSelect($useReadPdo)->prepare($query)
    436▕             );
    437▕
    438▕             $this->bindValues($statement, $this->prepareBindings($bindings));
    439▕

  1   vendor\laravel\framework\src\Illuminate\Database\Connection.php:435
  2   vendor\laravel\framework\src\Illuminate\Database\Connection.php:846

  ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\Notification\NotificationTest > it sendPush calls FCM send once for a user with one token                                                                                QueryException
  SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: hanapbuhay_test, SQL: insert into `device_tokens` (`user_id`, `fcm_token`, `device_type`, `updated_at`, `created_at`) values (14, valid-token-xyz, android, 2026-08-28 15:56:40, 2026-08-28 15:56:40))

  at vendor\laravel\framework\src\Illuminate\Database\MySqlConnection.php:47
     43▕             if ($this->pretending()) {
     44▕                 return true;
     45▕             }
     46▕
  ➜  47▕             $statement = $this->getPdo()->prepare($query);
     48▕
     49▕             $this->bindValues($statement, $this->prepareBindings($bindings));
     50▕
     51▕             $this->recordsHaveBeenModified();

  1   vendor\laravel\framework\src\Illuminate\Database\MySqlConnection.php:47
  2   vendor\laravel\framework\src\Illuminate\Database\Connection.php:846

  ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\Notification\NotificationTest > it sendPush deletes invalid token on MessagingException and does not throw                                                               QueryException
  SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hanapbuhay_test.device_tokens' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: hanapbuhay_test, SQL: insert into `device_tokens` (`user_id`, `fcm_token`, `device_type`, `updated_at`, `created_at`) values (15, bad-token-xyz, ios, 2026-08-28 15:56:40, 2026-08-28 15:56:40))

  at vendor\laravel\framework\src\Illuminate\Database\MySqlConnection.php:47
     43▕             if ($this->pretending()) {
     44▕                 return true;
     45▕             }
     46▕
  ➜  47▕             $statement = $this->getPdo()->prepare($query);
     48▕
     49▕             $this->bindValues($statement, $this->prepareBindings($bindings));
     50▕
     51▕             $this->recordsHaveBeenModified();

  1   vendor\laravel\framework\src\Illuminate\Database\MySqlConnection.php:47
  2   vendor\laravel\framework\src\Illuminate\Database\Connection.php:846


  Tests:    5 failed, 7 passed (20 assertions)
  Duration: 3.91s