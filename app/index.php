<?php
/**
 * PHP EPub Meta - App public entrypoint
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Sébastien Lucas <sebastien@slucas.fr>
 * @author Simon Schrape <simon@epubli.com> © 2015
 * @author mikespub
 */

use SebLucas\EPubMeta\EPub;
use SebLucas\EPubMeta\App\Util;
use SebLucas\EPubMeta\Tools\ZipEdit;

// modify this to point to your book directory
$bookdir = '/home/andi/Dropbox/ebooks/';
$bookdir = dirname(__DIR__) . '/test/data/';

// proxy google requests
if (isset($_GET['api'])) {
    header('application/json; charset=UTF-8');
    echo file_get_contents('https://www.googleapis.com/books/v1/volumes?q=' . rawurlencode($_GET['api']) . '&maxResults=25&printType=books&projection=full');
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$epub = null;
$error = null;
if (!empty($_REQUEST['book'])) {
    try {
        $book = preg_replace('/[^\w ._-]+/', '', $_REQUEST['book']);
        $book = basename($book . '.epub'); // no upper dirs, lowers might be supported later
        $epub = new EPub($bookdir . $book, ZipEdit::class);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// return image data
if (!empty($_REQUEST['img']) && isset($epub)) {
    $img = $epub->getCoverInfo();
    header('Content-Type: ' . $img['mime']);
    echo $img['data'];
    exit;
}

// save epub data
if (isset($_REQUEST['save']) && isset($epub)) {
    $epub->setTitle($_POST['title']);
    $epub->setDescription($_POST['description']);
    $epub->setLanguage($_POST['language']);
    $epub->setPublisher($_POST['publisher']);
    $epub->setCopyright($_POST['copyright']);
    $epub->setIsbn($_POST['isbn']);
    $epub->setSubjects($_POST['subjects']);

    $authors = [];
    foreach ((array) $_POST['authorname'] as $num => $name) {
        if ($name) {
            $as = $_POST['authoras'][$num];
            if (!$as) {
                $as = $name;
            }
            $authors[$as] = $name;
        }
    }
    $epub->setAuthors($authors);

    // handle image
    $cover = '';
    if (preg_match('/^https?:\/\//i', $_POST['coverurl'])) {
        $data = @file_get_contents($_POST['coverurl']);
        if ($data) {
            $cover = tempnam(sys_get_temp_dir(), 'epubcover');
            file_put_contents($cover, $data);
            unset($data);
        }
    } elseif(is_uploaded_file($_FILES['coverfile']['tmp_name'])) {
        $cover = $_FILES['coverfile']['tmp_name'];
    }
    if ($cover) {
        $info = @getimagesize($cover);
        if (preg_match('/^image\/(gif|jpe?g|png)$/', $info['mime'])) {
            $epub->setCoverInfo($cover, $info['mime']);
        } else {
            $error = 'Not a valid image file' . $cover;
        }
    }

    // save the ebook
    try {
        $epub->save();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    // clean up temporary cover file
    if ($cover) {
        @unlink($cover);
    }

    if (!$error) {
        // rename
        $author = array_keys($epub->getAuthors())[0];
        $title  = $epub->getTitle();
        $new    = Util::to_file($author . '-' . $title);
        $new    = $bookdir . $new . '.epub';
        $old    = $epub->file();
        if (realpath($new) != realpath($old)) {
            if (!@rename($old, $new)) {
                $new = $old; //rename failed, stay here
            }
        }
        $go = basename($new, '.epub');
        header('Location: ?book=' . rawurlencode($go));
        exit;
    }
}

$data = [
    'bookdir' => '',
    'booklist' => '',
    'license' => '',
    'alert' => '',
];
$data['bookdir'] = htmlspecialchars($bookdir);
$data['booklist'] = '';
$list = glob($bookdir . '/*.epub');
foreach ($list as $book) {
    $base = basename($book, '.epub');
    $name = Util::book_output($base);
    $data['booklist'] .= '<li ' . ($base == $_REQUEST['book'] ? 'class="active"' : '') . '>';
    $data['booklist'] .= '<a href="?book=' . htmlspecialchars($base) . '">' . $name . '</a>';
    $data['booklist'] .= '</li>';
}
if ($error) {
    $data['alert'] = "alert('" . htmlspecialchars($error) . "');";
}

if (empty($epub)) {
    $data['license'] = str_replace("\n\n", '</p><p>', htmlspecialchars(file_get_contents(dirname(__DIR__) . '/LICENSE')));

    $content = file_get_contents(dirname(__DIR__) . '/templates/index.html');
} else {
    $data['book'] = htmlspecialchars($_REQUEST['book']);
    $data['title'] = htmlspecialchars($epub->getTitle());
    $data['authors'] = '';
    $count = 0;
    foreach ($epub->getAuthors() as $as => $name) {
        $data['authors'] .= '<p>';
        $data['authors'] .= '<input type="text" name="authorname[' . $count . ']" value="' . htmlspecialchars($name) . '" />';
        $data['authors'] .= ' (<input type="text" name="authoras[' . $count . ']" value="' . htmlspecialchars($as) . '" />)';
        $data['authors'] .= '</p>';
        $count++;
    }
    $data['cover'] = '?book=' . htmlspecialchars($_REQUEST['book']) . '&amp;img=1';
    $c = $epub->getCoverInfo();
    $data['imgclass'] = $c['found'] ? 'hasimg' : 'noimg';
    $data['description'] = htmlspecialchars($epub->getDescription());
    $data['subjects'] = htmlspecialchars(join(', ', $epub->getSubjects()));
    $data['publisher'] = htmlspecialchars($epub->getPublisher());
    $data['copyright'] = htmlspecialchars($epub->getCopyright());
    $data['language'] = htmlspecialchars($epub->getLanguage());
    $data['isbn'] = htmlspecialchars($epub->getISBN());

    $content = file_get_contents(dirname(__DIR__) . '/templates/epub.html');
}

foreach ($data as $name => $value) {
    $content = preg_replace('/{{\s*' . $name . '\s*}}/', $value, $content);
}

header('Content-Type: text/html; charset=utf-8');
echo $content;
exit;
