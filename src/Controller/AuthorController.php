<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @Route("/authors")
 */
class AuthorController extends AbstractController
{
    /**
     * @Route("/", name="authors", methods={"GET"})
     */
    public function index(Request $request, SessionInterface $session): Response
    {
        $sort = (object) [
            'type' => 'asc',
            'field' => 'last_name'
        ];
        if ($request->query->get('sort_type')) {
            $sort->type = $request->query->get('sort_type');
        }

        if ($request->query->get('sort_field')) {
            $sort->field = $request->query->get('sort_field');
        }

        $page = $request->query->get('page');
        if (!$page) {
            $page = 1;
        }
        if ($request->query->get('limit')) {
            $limit = $request->query->get('limit');
            $session->set('limit', $limit);
        } else if ($session->get('limit')) {
            $limit = $session->get('limit');
        } else {
            $limit = 5;
            $session->set('limit', $limit);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $authors = $entityManager->getRepository(Author::class);
        $query = $authors->createQueryBuilder('u')
                        ->orderBy('u.'.$sort->field, $sort->type)
                        ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        $totalItems = count($paginator);
        $totalPage = ceil($totalItems / $limit);
        $paginator
            ->getQuery()
            ->setFirstResult($limit * ($page-1)) // set the offset
            ->setMaxResults($limit); // set the limit

        $authors = [];
        $numberItems = 0;
        foreach ($paginator as $pageItem) {
            $authors[] = $pageItem;
            $numberItems++;
        }

        $message = $session->get('message');
        $session->set('message', null);

        if (!$authors) {
            $message = (object) ['type' => 'info', 'content' => 'No authors found.'];
        }

        return $this->render('author/index.html.twig', [
            'authors' => $authors,
            'fields' => [
                'first_name' => 'First Name',
                'last_name' => 'Last Name'
            ],
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page-1)*$limit,
            'limitOptions' => [5, 10, 20, 50, 100],
            'totalPage' => $totalPage,
            'totalItems' => $totalItems,
            'numberItems' => $numberItems,
            'message' => $message,
            'sort' => $sort,
        ]);
    }

    /**
     * @Route("/new", name="author_new")
     */
    public function create(): Response
    {
        return $this->render('author/create.html.twig');
    }

    /**
     * @Route("", name="author_store", methods={"POST"})
     */
    public function store(Request $request, SessionInterface $session): Response
    {
        $authorRequest = $request->request->get('author');
        $author = new Author();
        $author->setFirstName($authorRequest['first_name'])
                ->setLastName($authorRequest['last_name']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($author);
        $entityManager->flush();

        $message = (object) ['type' => 'success', 'content' => 'Author was created!'];
        $session->set('message', $message);

        return $this->redirectToRoute('authors');
    }

    /**
     * @Route("/{author_id}", name="author_show", methods={"GET"})
     */
    public function show($author_id): Response
    {
        $author = $this->getDoctrine()->getRepository(Author::class)->find($author_id);
        if (!$author) {
            throw $this->createNotFoundException('Not author found for id '.$author_id);
        }

        return $this->render('author/show.html.twig', [
            'author' => $author,
        ]);
    }

    /**
     * @Route("/{author_id}/edit", name="author_edit", methods={"GET"})
     */
    public function edit($author_id): Response
    {
        $author = $this->getDoctrine()->getRepository(Author::class)->find($author_id);
        if (!$author) {
            throw $this->createNotFoundException('Not author found for id '.$author_id);
        }

        return $this->render('author/edit.html.twig', [
            'author' => $author,
        ]);
    }

    /**
     * @Route("/{author_id}/edit", name="author_update", methods={"POST"})
     */
    public function update($author_id, Request $request, SessionInterface $session): Response
    {
        $author = $this->getDoctrine()->getRepository(Author::class)->find($author_id);
        if (!$author) {
            throw $this->createNotFoundException('Not author found for id '.$author_id);
        }

        $authorRequest = $request->request->get('author');
        $author->setFirstName($authorRequest['first_name'])
                ->setLastName($authorRequest['last_name']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->flush();

        $message = (object) ['type' => 'success', 'content' => 'Author was updated!'];
        $session->set('message', $message);

        return $this->redirectToRoute('authors');
    }

    /**
     * @Route("/{author_id}/delete", name="author_delete", methods={"GET"})
     */
    public function destroy($author_id, SessionInterface $session): Response
    {
        $author = $this->getDoctrine()->getRepository(Author::class)->find($author_id);
        if (!$author) {
            throw $this->createNotFoundException('Not author found for id '.$author_id);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $books = $entityManager->getRepository(Book::class)->findBy(['author_id' => $author_id]);
        if ($books) {
            $message = (object) ['type' => 'danger', 'content' => 'Author has books. Not author deleted!'];
            $session->set('message', $message);
            return $this->redirectToRoute('authors', ['author_id' => $author_id]);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($author);
        $entityManager->flush();

        $message = (object) ['type' => 'success', 'content' => 'Author was deleted!'];
        $session->set('message', $message);

        return $this->redirectToRoute('authors');
    }
}
