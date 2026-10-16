<?php

namespace App\Controller;

use App\Cms\App3CachePurger;
use App\Cms\ValidationErrors;
use App\Forms\FormAdmin;
use App\Security\EditorialVoter;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Formulaires d'inscription dans app4 (PAGE_CMS.md § 6) : Rédacteurs et profils 1–2 (Q-P4-6) ; suppression : 1–2. */
#[Route('/admin/cms/forms')]
#[IsGranted(EditorialVoter::EDIT)]
#[OA\Tag(name: '40. App4 - Editorial')]
class AdminCmsFormsController extends AdminCmsBaseController
{
    public function __construct(Connection $connection, App3CachePurger $purger, private readonly FormAdmin $forms)
    {
        parent::__construct($connection, $purger);
    }

    #[Route('', name: 'admin_cms_forms', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->ok($this->forms->list());
    }

    #[Route('/{id}', name: 'admin_cms_form', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $form = $this->forms->get($id);

        return $form === null ? $this->notFound() : $this->ok($form);
    }

    #[Route('', name: 'admin_cms_form_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->save(null, $request);
    }

    #[Route('/{id}', name: 'admin_cms_form_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        return $this->forms->get($id) === null ? $this->notFound() : $this->save($id, $request);
    }

    #[Route('/{id}', name: 'admin_cms_form_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(EditorialVoter::ADMIN)]
    public function delete(int $id): JsonResponse
    {
        if (!$this->forms->delete($id)) {
            return $this->notFound();
        }
        $this->journal('Suppression formulaire', sprintf('Formulaire %d', $id));

        return $this->ok(['deleted' => true]);
    }

    #[Route('/{id}/entries', name: 'admin_cms_form_entries', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function entries(int $id): JsonResponse
    {
        $entries = $this->forms->entries($id);

        return $entries === null ? $this->notFound() : $this->ok($entries);
    }

    #[Route('/{id}/entries/{entryId}', name: 'admin_cms_form_entry_status', methods: ['PUT'], requirements: ['id' => '\d+', 'entryId' => '\d+'])]
    public function setEntryStatus(int $id, int $entryId, Request $request): JsonResponse
    {
        $status = (string) (self::body($request)['status'] ?? '');
        try {
            if (!$this->forms->setEntryStatus($id, $entryId, $status)) {
                return $this->notFound();
            }
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal('Statut inscription', sprintf('Formulaire %d, inscription %d : %s', $id, $entryId, $status));

        return $this->ok($this->forms->entries($id));
    }

    #[Route('/{id}/export', name: 'admin_cms_form_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function export(int $id, Request $request): Response
    {
        $rows = $this->forms->exportRows($id);
        if ($rows === null) {
            return $this->notFound();
        }
        $form = (array) $this->forms->get($id);
        $xlsx = $request->query->getString('format') === 'xlsx';
        $filename = sprintf('inscriptions-%s.%s', $form['slug'], $xlsx ? 'xlsx' : 'csv');
        $response = new StreamedResponse(static function () use ($rows, $xlsx): void {
            if ($xlsx) {
                $spreadsheet = new Spreadsheet();
                $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
                (new Xlsx($spreadsheet))->save('php://output');

                return;
            }
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            foreach ($rows as $row) {
                fputcsv($output, $row, ';', '"', '');
            }
            fclose($output);
        });
        $response->headers->set('Content-Type', $xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    #[Route('/{id}/mails', name: 'admin_cms_form_mails', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function mails(int $id): JsonResponse
    {
        $mails = $this->forms->mailLog($id);

        return $mails === null ? $this->notFound() : $this->ok($mails);
    }

    #[Route('/{id}/mails/{mailId}/resend', name: 'admin_cms_form_mail_resend', methods: ['POST'], requirements: ['id' => '\d+', 'mailId' => '\d+'])]
    public function resend(int $id, int $mailId): JsonResponse
    {
        $status = $this->forms->resend($id, $mailId);

        return $status === null ? $this->notFound() : $this->ok(['status' => $status]);
    }

    private function save(?int $id, Request $request): JsonResponse
    {
        try {
            $savedId = $this->forms->save($id, self::body($request), $this->userCode());
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal($id === null ? 'Création formulaire' : 'Modification formulaire', sprintf('Formulaire %d', $savedId));
        $form = (array) $this->forms->get($savedId);
        $this->purge(['/forms/' . $form['slug']]);

        return $this->ok($form, $id === null ? 201 : 200);
    }
}
