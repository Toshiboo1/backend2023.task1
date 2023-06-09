<?php

use DI\Container;
use Slim\Views\Twig;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);

$container->set('db', function () {
    $db = new \PDO("sqlite:" . __DIR__ . '/../database/database.sqlite');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(\PDO::ATTR_TIMEOUT, 5000);
    $db->exec("PRAGMA journal_mode = WAL");
    return $db;
});

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$twig = Twig::create(__DIR__ . '/../twig', ['cache' => false]);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->get('/users', function (Request $request, Response $response, $args) {
    // GET Query params
    $query_params = $request->getQueryParams();
    $format = $query_params['format']?? null;
    //dump($query_params);
    //die;

    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    if($format == 'json'){
        $payload = json_encode($users);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
    elseif($format =='text'){
        $printout = '';
        foreach($users as $user){
            $printout.=$user->first_name.' / '.$user->last_name.' / '.$user->email . PHP_EOL;
        }
        $response->getBody()->write($printout);
        return $response->withHeader('Content-Type', 'text/plain');
    }
    // dump($users);
    // die;
   
    $view = Twig::fromRequest($request);
    return $view->render($response, 'users.html', [
        'users' => $users
    ]);
});

$app->get('/users-by-header', function (Request $request, Response $response, $args) {
    $header=$request->getHeader('Accept')[0];

    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    if ($header == 'application/json') {
        $payload = json_encode($users);

        $response->getBody()->write($payload);
        return $response
                ->withHeader('Content-Type', 'application/json');
    }
    elseif ($header == 'text/plain') {
        $printout = '';
        foreach($users as $user) {
            $printout .= $user->first_name.' / '.$user->last_name.' / '.$user->email . PHP_EOL;
            
        }
        $response->getBody()->write($printout);
        return $response
                    ->withHeader('Content-Type', 'text/plain');
    }
    return $response->withStatus(404);
});

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];

    $db = $this->get('db');
    $sth = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $sth->bindValue(':id', $id);
    $sth->execute();

    $user = $sth->fetch(\PDO::FETCH_OBJ);
    if (!$user){
        return $response->withStatus(404);
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'user.html', [
        'user' => $user
    ]);
});

$app->post('/users', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    // получаем тело запроса
    $first_name = $parsedBody["first_name"];
    $last_name = $parsedBody["last_name"];
    $email = $parsedBody["email"];
    $sth = $db->prepare("INSERT INTO users (first_name, last_name, email) VALUES (?,?,?)");
    $sth->execute([$first_name, $last_name, $email]);
    return $response->withStatus(201);
});

$app->patch('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');

    $parsedBody = $request->getParsedBody();
    $first_name = $parsedBody["first_name"] ?? null;
    $last_name = $parsedBody["last_name"] ?? null;
    $email = $parsedBody["email"] ?? null;

    if($first_name){
        $sth = $db->prepare("UPDATE users SET first_name=? WHERE id=?");
        $sth->execute([$first_name, $id]);
    }
    if($last_name){
        $sth = $db->prepare("UPDATE users SET last_name=? WHERE id=?");
        $sth->execute([$last_name, $id]);
    }
    if($email){
        $sth = $db->prepare("UPDATE users SET email=? WHERE id=?");
        $sth->execute([$email, $id]);
    }

    //$sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    //$sth->execute([$first_name, $last_name, $email, $id]);
    return $response->withStatus(200);
});

$app->put('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();

    $first_name = $parsedBody["first_name"];
    $last_name = $parsedBody["last_name"];
    $email = $parsedBody["email"];

    $sth = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?');
    $sth->execute([$first_name, $last_name, $email, $id]);
    return $response->withStatus(302)->withHeader('Location', '/users');
    //dump($parsedBody);
    //die;
});

$app->delete('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
    $sth = $db->prepare('DELETE FROM users WHERE id=:id');
    $sth->bindValue(':id', $id);
    $sth->execute();

    return $response->withStatus(204);
});

$app->get('/download', function(Request $request, Response $response, $args){
    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    $printout = '';
        foreach($users as $user) {
            $printout .= $user->first_name.'    '.$user->last_name.'    '.$user->email . PHP_EOL;
            
        }
    $response->getBody()->write($printout);
    header('Content-type: application/pdf');
    header('Content-Disposition: attachment; filename="user_report_' . date('Y-m-d') . '.pdf"');

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->Write(0, $printout);
    $pdf->Output('user_report_' . date('Y-m-d') . '.pdf', 'D');
});

$methodOverrideMiddleware = new MethodOverrideMiddleware();
$app->add($methodOverrideMiddleware);

$app->add(TwigMiddleware::create($app, $twig));
$app->run();
