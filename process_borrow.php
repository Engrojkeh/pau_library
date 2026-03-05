<?php
// process_borrow.php
session_start();
include 'db_conn.php';

// Ensure user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Get Data
    $qr_data = htmlspecialchars(trim($_POST['qr_data']), ENT_QUOTES, 'UTF-8');
    $matric_no = htmlspecialchars(trim($_POST['matric_no']), ENT_QUOTES, 'UTF-8');
    $action = htmlspecialchars(trim($_POST['action']), ENT_QUOTES, 'UTF-8');

    // Extract ISBN (Remove "BOOK-" prefix)
    $isbn = str_replace("BOOK-", "", $qr_data);

    // 2. Find Book ID cleanly
    $book_query = $conn->prepare("SELECT id, status FROM books WHERE isbn = ?");
    $book_query->bind_param("s", $isbn);
    $book_query->execute();
    $book = $book_query->get_result()->fetch_assoc();
    $book_query->close();

    if (!$book) {
        header("Location: scan_book.php?msg=error&details=" . urlencode("Book with ISBN $isbn not found in database."));
        exit();
    }

    // --- LOGIC: BORROW ---
    if ($action == "borrow") {
        if (empty($matric_no)) {
            header("Location: scan_book.php?msg=error&details=" . urlencode("Student Matriculation Number is REQUIRED to borrow a book."));
            exit();
        }

        // Find Student ID
        $stu_query = $conn->prepare("SELECT id FROM students WHERE matric_no = ?");
        $stu_query->bind_param("s", $matric_no);
        $stu_query->execute();
        $student = $stu_query->get_result()->fetch_assoc();
        $stu_query->close();

        if (!$student) {
            header("Location: scan_book.php?msg=error&details=" . urlencode("Student with Matric No $matric_no not found."));
            exit();
        }

        if ($book['status'] == 'Borrowed') {
            header("Location: scan_book.php?msg=error&details=" . urlencode("This book is already securely logged as Borrowed!"));
            exit();
        }

        // Set Due Date (7 days from now)
        $borrow_date = date("Y-m-d");
        $due_date = date("Y-m-d", strtotime("+7 days"));

        // Insert Transaction safely
        $sql = "INSERT INTO transactions (student_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'Active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $student['id'], $book['id'], $borrow_date, $due_date);
        
        if ($stmt->execute()) {
            $stmt->close();
            // Update Book Status safely
            $update_book = $conn->prepare("UPDATE books SET status = 'Borrowed' WHERE id = ?");
            $update_book->bind_param("i", $book['id']);
            $update_book->execute();
            $update_book->close();

            header("Location: scan_book.php?msg=borrow_success");
            exit();
        } else {
            header("Location: scan_book.php?msg=error&details=" . urlencode("Failed to log transaction."));
            exit();
        }
    }

    // --- LOGIC: RETURN ---
    elseif ($action == "return") {
        // For Return, we just need to find an Active Transaction for this book!
        // Matric No is optional, but if provided, we can filter by it.
        if (!empty($matric_no)) {
            $stu_query = $conn->prepare("SELECT id FROM students WHERE matric_no = ?");
            $stu_query->bind_param("s", $matric_no);
            $stu_query->execute();
            $student = $stu_query->get_result()->fetch_assoc();
            $stu_query->close();

            if (!$student) {
                header("Location: scan_book.php?msg=error&details=" . urlencode("Student with Matric No $matric_no not found."));
                exit();
            }
            
            $trans_query = "SELECT id, due_date FROM transactions WHERE book_id = ? AND student_id = ? AND status = 'Active'";
            $stmt = $conn->prepare($trans_query);
            $stmt->bind_param("ii", $book['id'], $student['id']);
        } else {
            // Find any active transaction for this book regardless of student
            $trans_query = "SELECT id, due_date FROM transactions WHERE book_id = ? AND status = 'Active'";
            $stmt = $conn->prepare($trans_query);
            $stmt->bind_param("i", $book['id']);
        }
        
        $stmt->execute();
        $trans = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($trans) {
            // Calculate Fine
            $today = new DateTime();
            $due = new DateTime($trans['due_date']);
            $fine = 0;
            
            if ($today > $due) {
                $diff = $today->diff($due);
                $fine = $diff->days * 100; // 100 Naira per day
            }

            // Update Transaction cleanly
            $update = $conn->prepare("UPDATE transactions SET return_date = NOW(), fine_amount = ?, status = 'Returned' WHERE id = ?");
            $update->bind_param("di", $fine, $trans['id']);
            $update->execute();
            $update->close();

            // Update Book Status cleanly
            $update_book = $conn->prepare("UPDATE books SET status = 'Available' WHERE id = ?");
            $update_book->bind_param("i", $book['id']);
            $update_book->execute();
            $update_book->close();

            header("Location: scan_book.php?msg=return_success&fine=" . urlencode($fine));
            exit();
        } else {
            header("Location: scan_book.php?msg=error&details=" . urlencode("No active borrowing record found for this book."));
            exit();
        }
    }
} else {
    header("Location: scan_book.php");
    exit();
}
?>