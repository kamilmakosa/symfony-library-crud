<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Repository\BookRepository;
use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @Route("/books")
 */
class BookController extends AbstractController
{
    /**
     * @Route("/", name="books", methods={"GET"})
     */
    public function index(Request $request, SessionInterface $session, BookRepository $bookRepository): Response
    {
        $sort = (object) [
            'type' => 'asc',
            'field' => 'title'
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
            $session->set('books_limit', $limit);
        } else if ($session->get('books_limit')) {
            $limit = $session->get('books_limit');
        } else {
            $limit = 5;
            $session->set('books_limit', $limit);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $books = $entityManager->getRepository(Book::class);
        $query = $books->createQueryBuilder('u')
                        // ->innerJoin(Author::class, 'r', Join::WITH, 'u.id = r.id')
                        ->orderBy('u.'.$sort->field, $sort->type)
                        ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        $totalItems = count($paginator);
        $totalPage = ceil($totalItems / $limit);
        $paginator
            ->getQuery()
            ->setFirstResult($limit * ($page-1)) // set the offset
            ->setMaxResults($limit); // set the limit

        $books = [];
        $numberItems = 0;
        foreach ($paginator as $pageItem) {
            $author = $this->getDoctrine()->getRepository(Author::class)->find($pageItem->getAuthorId());
            $pageItem->author = $author->getFirstName().' '.$author->getLastName();
            $books[] = $pageItem;
            $numberItems++;
        }

        $message = $session->get('message');
        $session->set('message', null);

        if (!$books) {
            $message = (object) ['type' => 'info', 'content' => 'No books found.'];
        }

        return $this->render('book/index.html.twig', [
            'books' => $books,
            'fields' => [
                'title' => 'Title',
                'author' => 'Author',
                'year' => 'Year',
                'ISBN' => 'ISBN',
                // 'coverImage' => 'Cover',
            ],
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page-1)*$limit,
            'limitOptions' => [5, 10, 20, 50, 100],
            'totalPage' => $totalPage,
            'totalItems' => $totalItems,
            'numberItems' => $numberItems,
            'message' => $message,
            'sort' => $sort
        ]);
    }

    /**
     * @Route("/new", name="book_new", methods={"GET"})
     */
    public function create(): Response
    {
        $authors = $this->getDoctrine()->getRepository(Author::class)->findAll();

        return $this->render('book/create.html.twig', [
            'authors' => $authors,
            'authorsNumber' => count($authors),
        ]);
    }

    /**
     * @Route("", name="book_store", methods={"POST"})
     */
    public function store(Request $request, SessionInterface $session, FileUploader $fileUploader): Response
    {
        $image = $request->files->get('book_cover');
        $fileName = $fileUploader->setTargetDirectory($this->getCoverDirectory());
        $fileName = $fileUploader->upload($image);

        $bookRequest = $request->request->get('book');
        $book = new Book();
        $book->setTitle($bookRequest['title'])
            ->setYear($bookRequest['year'])
            ->setISBN($bookRequest['ISBN'])
            ->setAuthorId($bookRequest['author_id'])
            ->setCoverImage($fileName);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($book);
        $entityManager->flush();

        $message = (object) ['type' => 'success', 'content' => 'Book created!'];
        $session->set('message', $message);

        return $this->redirectToRoute('books');
    }

    /**
     * @Route("/{book_id}", name="book_show", methods={"GET"})
     */
    public function show($book_id): Response
    {
        $book = $this->getDoctrine()->getRepository(Book::class)->find($book_id);
        if (!$book) {
            throw $this->createNotFoundException('Not book found for id '.$book_id);
        }

        $author_id = $book->getAuthorId();
        $author = $this->getDoctrine()->getRepository(Author::class)->find($author_id);
        if (!$author) {
            throw $this->createNotFoundException('Not book found for id '.$author_id);
        }

        $book->author = $author->getFirstName().' '.$author->getLastName();

        return $this->render('book/show.html.twig', [
            'book' => $book
        ]);
    }

    /**
     * @Route("/{book_id}/edit", name="book_edit", methods={"GET"})
     */
    public function edit($book_id): Response
    {
        $book = $this->getDoctrine()->getRepository(Book::class)->find($book_id);
        if (!$book) {
            throw $this->createNotFoundException('Not book found for id '.$book_id);
        }

        $authors = $this->getDoctrine()->getRepository(Author::class)->findAll();

        return $this->render('book/edit.html.twig', [
            'book' => $book,
            'authors' => $authors,
        ]);
    }

    /**
     * @Route("/{book_id}/edit", name="book_update", methods={"POST"})
     */
    public function update($book_id, Request $request, SessionInterface $session): Response
    {
        $book = $this->getDoctrine()->getRepository(Book::class)->find($book_id);
        if (!$book) {
            throw $this->createNotFoundException('Not book found for id '.$book_id);
        }

        $bookRequest = $request->request->get('book');
        $book->setTitle($bookRequest['title'])
            ->setYear($bookRequest['year'])
            ->setISBN($bookRequest['ISBN'])
            ->setAuthorId($bookRequest['author_id'])
            ->setCoverImage($bookRequest['cover_image']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->flush();

        $message = (object) ['type' => 'success', 'content' => 'Book was updated!'];
        $session->set('message', $message);

        return $this->redirectToRoute('books');
    }

    /**
     * @Route("/{book_id}/delete", name="book_delete", methods={"GET"})
     */
    public function destroy($book_id, SessionInterface $session): Response
    {
        $book = $this->getDoctrine()->getRepository(Book::class)->find($book_id);
        if (!$book) {
            throw $this->createNotFoundException('Not book found for id '.$book_id);
        }

        unlink($this->getCoverDirectory()."/".$book->getCoverImage());

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($book);
        $entityManager->flush();

        $message = (object) ['type' => 'success', 'content' => 'Book was deleted!'];
        $session->set('message', $message);

        return $this->redirectToRoute('books');
    }

    private function getCoverDirectory() {
        return realpath(dirname( __FILE__ ) . '/../../public/covers');
    }
}
