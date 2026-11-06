-- Safe to import again: rooms and add-ons already present are left alone, and the sample booking is reset.
USE maison_aurelle;
INSERT IGNORE INTO room_types (slug,name,tier,description,size_sqm,max_adults,max_children,bed_config,`view`,base_price,total_units,amenities,hero_image,gallery,sort_order) VALUES
('deluxe','Deluxe Room','room','A calm, tailored room in charcoal and linen with a deep soaking tub.',38,2,1,'1 King','City skyline',280,12,'["Rain shower","Nespresso","Smart TV","Turndown"]','assets/img/rooms/deluxe.jpg','[]',1),
('premier','Premier Room','room','Higher floor, wider windows and a reading nook made for slow mornings.',45,2,2,'1 King or 2 Twins','High-floor city',360,10,'["Soaking tub","Lounge nook","Minibar","Turndown"]','assets/img/rooms/premier.jpg','[]',2),
('junior-suite','Junior Suite','suite','Open-plan living and sleeping areas with a marble bath and dressing room.',62,3,1,'1 King','Park and skyline',520,6,'["Dressing room","Marble bath","Espresso bar","Butler on request"]','assets/img/rooms/junior-suite.jpg','[]',3),
('executive-suite','Executive Suite','suite','A separate lounge, dining table for four and private lounge access.',85,3,2,'1 King','Panoramic',760,4,'["Lounge access","Dining table","Walk-in wardrobe","Butler"]','assets/img/rooms/executive-suite.jpg','[]',4),
('garden-villa','Garden Villa','suite','A private villa with a plunge pool and a walled garden terrace.',120,4,2,'1 King and 1 Queen','Private garden',980,2,'["Plunge pool","Terrace","Private entrance","Butler"]','assets/img/rooms/garden-villa.jpg','[]',5),
('presidential-suite','Presidential Suite','suite','The top floor in full: two bedrooms, a library and a 360-degree terrace.',210,4,2,'2 Kings','360 skyline',1850,1,'["Terrace","Library","Private chef on request","Chauffeur"]','assets/img/rooms/presidential-suite.jpg','[]',6);

INSERT IGNORE INTO addons (slug,name,category,description,price,pricing_type,savings_note,sort_order) VALUES
('breakfast','Daily breakfast','dining','Chef-led breakfast served in the dining room or in-suite.',32,'per_person_per_night',NULL,1),
('lunch','Garden lunch','dining','Three courses on the terrace, wine pairing optional.',48,'per_person',NULL,2),
('dinner','Tasting dinner','dining','Seven-course seasonal tasting menu.',95,'per_person',NULL,3),
('massage','Signature massage (90 min)','spa','Deep-tissue and aromatherapy with warm stones.',180,'per_person',NULL,4),
('ritual','Aurelle gold ritual (2 h)','spa','Body polish, facial and scalp treatment.',260,'per_person',NULL,5),
('couples-spa','Couples spa suite (2 h)','spa','Private treatment suite with tea and fruit.',420,'per_stay',NULL,6),
('transfer','Airport transfer','transport','Private car, one way, with water and towels.',85,'per_stay',NULL,7),
('late-checkout','Late checkout (4 pm)','experience','Keep your room until 4 pm, subject to availability.',70,'per_stay',NULL,8),
('personal-training','Personal training session','experience','One hour in the gym with a coach.',90,'per_person',NULL,9),
('romance-escape','Romance Escape','bundle','Champagne on arrival, tasting dinner for two, couples spa and late checkout.',640,'per_stay','Save 18%',10),
('wellness-retreat','Wellness Retreat','bundle','Daily breakfast, massage and a training session for each guest.',520,'per_person','Save 15%',11),
('gourmet-weekend','Gourmet Weekend','bundle','Breakfast daily, tasting dinner and a garden lunch for two.',560,'per_stay','Save 12%',12);

-- Sample booking, for trying Manage Reservation and the front desk view without booking first.
-- Look it up with booking ID MA-SAMPLE22 and email sample.guest@example.test.
-- Its dates are relative to the day you import, so importing again brings it back to a fresh, changeable state.
-- Junior Suite, 3 nights, 2 adults, daily breakfast. These totals are what quote() calculates:
--   room 1560.00 + breakfast 192.00 = 1752.00, service 10% = 175.20, tax 8% of 1927.20 = 154.18, total 2081.38.
INSERT INTO guests (first_name,last_name,email,phone)
SELECT 'Sample','Guest','sample.guest@example.test','+1 202 555 0143' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM guests WHERE email = 'sample.guest@example.test');

INSERT INTO bookings (booking_ref,guest_id,room_type_id,check_in,check_out,nights,adults,status,payment_status,subtotal,addons_total,service_charge,tax,total,currency,special_requests)
SELECT 'MA-SAMPLE22', g.id, r.id, CURDATE() + INTERVAL 30 DAY, CURDATE() + INTERVAL 33 DAY, 3, 2, 'confirmed', 'unpaid', 1560.00, 192.00, 175.20, 154.18, 2081.38, 'USD', 'Arrival: 16:30. A quiet room, please.'
FROM guests g JOIN room_types r ON r.slug = 'junior-suite'
WHERE g.email = 'sample.guest@example.test'
ON DUPLICATE KEY UPDATE room_type_id = VALUES(room_type_id), check_in = VALUES(check_in), check_out = VALUES(check_out), nights = 3, adults = 2, status = 'confirmed', payment_status = 'unpaid',
  subtotal = 1560.00, addons_total = 192.00, service_charge = 175.20, tax = 154.18, total = 2081.38, cancellation_fee = 0, cancelled_at = NULL;

DELETE FROM booking_addons WHERE booking_id = (SELECT id FROM bookings WHERE booking_ref = 'MA-SAMPLE22');
INSERT INTO booking_addons (booking_id,addon_id,quantity,unit_price,line_total)
SELECT b.id, a.id, 6, 32.00, 192.00 FROM bookings b JOIN addons a ON a.slug = 'breakfast' WHERE b.booking_ref = 'MA-SAMPLE22';

INSERT INTO booking_events (booking_id,event_type,payload)
SELECT b.id, 'created', '{"ref":"MA-SAMPLE22","total":2081.38}' FROM bookings b
WHERE b.booking_ref = 'MA-SAMPLE22' AND NOT EXISTS (SELECT 1 FROM booking_events e WHERE e.booking_id = b.id AND e.event_type = 'created');

INSERT INTO inquiries (booking_id,name,email,topic,message)
SELECT b.id, 'Sample Guest', 'sample.guest@example.test', 'Arrival and transfer', 'We land at 14:00. Could you arrange a car from the airport?' FROM bookings b
WHERE b.booking_ref = 'MA-SAMPLE22' AND NOT EXISTS (SELECT 1 FROM inquiries i WHERE i.booking_id = b.id AND i.topic = 'Arrival and transfer');
