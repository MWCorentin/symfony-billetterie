<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\Booking;
use AppBundle\Form\Type\BookingType;
use AppBundle\Manager\BookingManager;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Event;
use AppBundle\Entity\User;
use AppBundle\Controller\Traits\UtilitiesTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class BookingController
 *
 * @Route("/reservation")
 */
class BookingController extends Controller
{
    use UtilitiesTrait;

    /**
     * Liste des réservations par événement
     *
     * @Route("/", name="admin_booking_index")
     * @Method({"GET"})
     *
     * @return Response
     */
    public function indexAction()
    {
        $em = $this->getDoctrine();

        $bookings = $em->getRepository('AppBundle:Booking')->findAll();

        /** @var Event[] $events */
        $events = $em->getRepository('AppBundle:Event')->findAll();

        return $this->render(
            'admin/booking/index.html.twig',
            [
                'bookings' => $bookings,
                'events'   => $events,
            ]
        );
    }

    /**
     * Exporter les réservations
     *
     * @Route("/exporter-toutes-les-reservations", name="admin_booking_export_all")
     * @Method({"GET"})
     *
     * @return Response
     */
    public function exportAllBookingAction()
    {
        try {
            /** @var Booking[]|ArrayCollection $bookings */
            $bookings = $this->getDoctrine()->getRepository('AppBundle:Booking')->findForExport();
            /** @var BookingManager $bookingManager */
            $bookingManager = $this->get('app.manager.booking');

            return $bookingManager->exportBooking($this->getUser(), $bookings);
        } catch (Exception $e) {
            $this->addFlash('danger', 'flash.admin.booking.export.danger');
        }

        return $this->redirectToRoute('admin_booking_index');
    }

    /**
     * Exporter les réservations
     *
     * @param int $id
     *
     * @Route("/exporter-la-reservation/{id}", name="admin_booking_export")
     * @Method({"GET"})
     *
     * @return RedirectResponse|StreamedResponse
     */
    public function exportBookingAction(int $id)
    {
        try {
            /** @var Booking[]|ArrayCollection $booking */
            $booking = $this->getDoctrine()->getRepository('AppBundle:Booking')->findBy(
                [
                    'id' => $id,
                ]
            );
            /** @var BookingManager $bookingManager */
            $bookingManager = $this->get('app.manager.booking');

            return $bookingManager->exportBooking($this->getUser(), $booking);
        } catch (Exception $e) {
            $this->addFlash('danger', 'flash.admin.booking.export.danger');
        }

        return $this->redirectToRoute('admin_booking_index');
    }

    /**
     * Call ajax pour tri liste réservations par événement
     *
     * @Route("/ajax-liste-reservation", name="admin_ajax_booking_list_index")
     * @Method({"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function ajaxBookingFilterAction(Request $request)
    {
        $event = $request->request->get('event');
        $bookings = $this->getDoctrine()->getRepository('AppBundle:Booking')->findBy(
            ['event' => $event]
        );

        return $this->render(
            'admin/booking/_booking_list.html.twig',
            [
                'bookings' => $bookings,
            ]
        );
    }

    /**
     * Ajout d'une réservation
     *
     * @Route("/ajouter", name="admin_booking_add")
     * @param Request $request
     *
     * @return Response
     */
    public function addAction(Request $request)
    {
        /** @var Booking $booking */
        $booking = new Booking();

        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            /** @var BookingManager $bookingManager */
            $bookingManager = $this->get('app.manager.booking');

            /** @var BookingManager $bookingManager */
            $stockManager = $this->get('app.manager.stock');

            // evenemnt form ?
            foreach ($booking->getTickets() as $ticket) {
                if ($stockManager->manageStock(
                    false,
                    $booking->getEvent(),
                    $booking->getTicketCategory(),
                    $booking->getTicketCount()
                )
                ) {
                    $ticketUser = $bookingManager->manageUser($ticket->getUser());
                    $ticket->setBooking($booking);

                    if ($booking->getTickets()[key($booking->getTickets())] == $ticket) {
                        $booking->setMainUser($ticketUser);
                    } else {
                        $em->persist($ticketUser);
                        $booking->addSecondaryUser($ticketUser);
                    }

                    $ticket->setUser($ticketUser);

                    try {
                        $em->persist($booking);
                        $em->flush();

                        $this->addFlash('success', 'flash.admin.booking.add.success');

                        return $this->redirectToRoute('admin_booking_index');
                    } catch (\Exception $e) {
                        $this->addFlash('danger', 'flash.admin.booking.add.danger');
                    }
                } else {
                    $this->addFlash('danger', 'flash.admin.booking.insufficientStock.danger');
                }
            }
        }

        return $this->render(
            'admin/booking/create.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Modification d'une réservation
     *
     * @param Request $request
     * @param int  $id
     *
     * @return RedirectResponse|Response
     *
     * @Route("/editer/{id}", name="admin_booking_edit")
     */
    public function editAction(Request $request, int $id)
    {
        $em = $this->getDoctrine();

        /** @var Booking $booking */
        $booking = $em->getRepository('AppBundle:Booking')->findOneBy(['id' => $id]);
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->getManager()->persist($booking);
                $em->getManager()->flush();
                $this->addFlash('success', 'flash.admin.event.edit.success');

                return $this->redirectToRoute('admin_booking_index');
            } catch (\Exception $e) {
                dump($e);
                $this->addFlash('danger', 'flash.admin.booking.edit.danger');
            }
        }

        return $this->render('admin/booking/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Suppression d'une réservation
     *
     * @Route("/supprimer/{booking}", name="admin_booking_delete")
     * @param Booking $booking
     *
     * @return RedirectResponse
     */
    public function deleteAction(Booking $booking)
    {
        // Update stock before deleting
        $em = $this->getDoctrine()->getManager();

        /** @var BookingManager $bookingManager */
        $stockManager = $this->get('app.manager.stock');

        $stockManager->manageStock(
            true,
            $booking->getEvent(),
            $booking->getTicketCategory(),
            $booking->getTicketCount()
        );
        $em->remove($booking);

        try {
            $em->flush();
            $this->addFlash('success', 'flash.admin.booking.delete.success');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'flash.admin.booking.delete.danger');
        }

        return $this->redirectToRoute('admin_booking_index');
    }
}
