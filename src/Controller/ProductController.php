<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Form\ProductEditType;
use Symfony\Component\Form\FormView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductController extends AbstractController
{
    /**
     * @Route("/")
     */
    public function listProducts(Request $request)
    {
        $product = $this->getAllProductsMySQL();
        $form = $this->createForm(ProductType::class, $product);

        $form->handleRequest($request);
        if($form->isSubmitted()){
            $this->addProductMySQL($form->getData());
            return $this->redirect("/");
        }

        return $this->render('product/index.html.twig', [
            "products" => $product,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/delete/{id}")
     */
    public function deleteProduct(Request $request, $id)
    {
        $product = $this->getProductByID($id);

        if($product)
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($product);
            $entityManager->flush();
        }
        
        return $this->redirect('/');
    }

    /**
     * @Route("/edit/{id}")
     */
    public function editProduct(Request $request, $id)
    {
        $product = $this->getProductByIDMySQL($id);
        $form = $this->createForm(ProductEditType::class);   
        $form->get('amount')->setData($product->getAmount());
        $form->get('name')->setData($product->getName());
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->get('submit')->isClicked()){
            $this->saveProductMySQL($form->getData(), $id);
            return $this->redirect("/");
        }
        elseif($form->isSubmitted() && $form->get('cancel')->isClicked()){
            return $this->redirect("/");
        }
        return $this->render('product/edit.html.twig', [
            'form' => $form->createView()
        ]);
    }

    public function addProduct($data)
    {
        $product = new Product();
        $product->setName($data["name"]);
        $product->setAmount($data["amount"]);
        
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($product);
        $entityManager->flush();
    }

    private function getProductRepository()
    {
        $repo = $this->getDoctrine()
            ->getRepository(Product::class);

        if (!$repo) {
            throw $this->createNotFoundException(
                'No products found'
            );
        }

        return $repo;
    }

    private function getAllProducts()
    {
        $repo = $this->getProductRepository();

        return $repo->findAll();
    }

    private function getProductByID(int $id)
    {
        if($id <= 0){
            throw $this->createNotFoundException('ID ' . $id . ' is not valid');
        };

        $repo = $this->getProductRepository();

        return $repo->find($id);
    }

    private function getConnection()
    {
        return new \mysqli($_ENV['DB_SERVER'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
    }

    private function getAllProductsMySQL()
    {
        $products = [];
        $conn = $this->getConnection();
        $sql = "SELECT * FROM product";
        $result = $conn->query($sql);
        
        if($result->num_rows > 0)
        {
            while($row = $result->fetch_assoc()){
                $product = new Product();
                $product->setId($row["ID"]);
                $product->setName($row["Name"]);
                $product->setAmount($row["Amount"]);
                $products[] = $product;
            }
        }

        $conn->close();

        return $products;
    }

    private function getProductByIDMySQL(int $id)
    {
        if($id <= 0){throw $this->createNotFoundException('ID ' . $id . ' is not valid');};

        $conn = $this->getConnection();
        $product = null;

        /* create a prepared statement */
        if ($stmt = $conn->prepare("SELECT * FROM product WHERE ID=?")) {

            /* bind parameters for markers */
            $stmt->bind_param("i", $id);

            /* execute query */
            $stmt->execute();

            /* bind result variables */
            $stmt->bind_result($id, $name, $price, $amount);

            /* fetch value */
            $stmt->fetch();

            $product = new Product();
            $product->setId($id);
            $product->setName($name);
            $product->setPrice($price);
            $product->setAmount($amount);

            /* close statement */
            $stmt->close();
        }

        $conn->close();
        
        return $product;
    }

    public function addProductMySQL($data)
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("INSERT INTO product (Name, Amount) VALUES (?, ?)");
        $stmt->bind_param('si', $name, $amount);
        
        $name = $data["name"];
        $amount = $data["amount"];

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }

    public function saveProductMySQL($data, $id)
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("UPDATE product SET Name=?, Amount=? WHERE ID=?");
        $stmt->bind_param('sii', $name, $amount, $id);
        
        $name = $data["name"];
        $amount = $data["amount"];

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }

    /**
     * @Route("/deleteMySQL/{id}")
     */
    public function deleteProductMySQL(Request $request, $id)
    {
        $conn = $this->getConnection();
        $sql = "DELETE FROM product WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        return $this->redirect('/');
    }
}
