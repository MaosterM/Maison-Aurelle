<?php
declare(strict_types=1);

/**
 * One source of hotel facts for the Contact FAQ, the concierge's instructions and its keyword fallback.
 * 'page' => shown on the Contact page. 'match' => regex that selects this answer when there is no LLM.
 */
function hotel_faqs(): array {
    return [
        ['q' => 'When can I check in and check out?', 'page' => true, 'match' => 'check.?in|check.?out|arriv|depart|late check',
         'a' => 'Check-in opens at 15:00 and check-out is by 12:00. Late checkout until 16:00 can be added to your stay, subject to availability.'],
        ['q' => 'How do I change or cancel my reservation?', 'page' => true, 'match' => 'cancel|refund|chang|modif|amend|reschedul|manage|my (booking|reservation)',
         'a' => 'Use Manage Reservation with your booking ID and email. Cancellation is free until ' . FREE_CANCEL_HOURS . ' hours before 15:00 on your check-in day. After that, the first night is charged.'],
        ['q' => 'When do I pay?', 'page' => true, 'match' => '\bpay|\bcard\b|deposit|credit',
         'a' => 'You pay at the hotel. We never ask for card details on this website.'],
        ['q' => 'What is included in the price?', 'page' => true, 'match' => 'includ|\btax|service charge|\bfees?\b|total',
         'a' => 'Room rates are shown before a ' . round(SERVICE_RATE * 100) . '% service charge and ' . round(TAX_RATE * 100) . '% tax. Both are itemized before you confirm, so the total you see is the total you pay.'],
        ['q' => 'Can you arrange an airport transfer?', 'page' => true, 'match' => 'airport|transfer|\bcar\b|taxi|chauffeur|pick.?up',
         'a' => 'Yes. Add a private car when you choose your extras, or ask the front desk any time before you arrive.'],
        ['q' => 'When are the spa and gym open?', 'page' => true, 'match' => '\bspa\b|\bgym\b|massage|treatment|fitness|workout',
         'a' => 'The spa is open daily from 09:00 to 21:00. The gym is open to guests from 05:30 to 23:00, with a coach on the floor until 20:00.'],
        ['q' => 'Where are you and how do I reach you?', 'page' => false, 'match' => 'where|address|location|direction|find you|phone|call|email|contact|parking|valet',
         'a' => 'We are at ' . HOTEL_ADDRESS . '. The front desk is open 24 hours on ' . HOTEL_PHONE . ', or write to ' . HOTEL_EMAIL . '. Valet parking is at the main entrance.'],
    ];
}
