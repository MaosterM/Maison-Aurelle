USE maison_aurelle;
INSERT INTO room_types (slug,name,tier,description,size_sqm,max_adults,max_children,bed_config,`view`,base_price,total_units,amenities,hero_image,gallery,sort_order) VALUES
('deluxe','Deluxe Room','room','A calm, tailored room in charcoal and linen with a deep soaking tub.',38,2,1,'1 King','City skyline',280,12,'["Rain shower","Nespresso","Smart TV","Turndown"]','assets/img/rooms/deluxe.jpg','[]',1),
('premier','Premier Room','room','Higher floor, wider windows and a reading nook made for slow mornings.',45,2,2,'1 King or 2 Twins','High-floor city',360,10,'["Soaking tub","Lounge nook","Minibar","Turndown"]','assets/img/rooms/premier.jpg','[]',2),
('junior-suite','Junior Suite','suite','Open-plan living and sleeping areas with a marble bath and dressing room.',62,3,1,'1 King','Park and skyline',520,6,'["Dressing room","Marble bath","Espresso bar","Butler on request"]','assets/img/rooms/junior-suite.jpg','[]',3),
('executive-suite','Executive Suite','suite','A separate lounge, dining table for four and private lounge access.',85,3,2,'1 King','Panoramic',760,4,'["Lounge access","Dining table","Walk-in wardrobe","Butler"]','assets/img/rooms/executive-suite.jpg','[]',4),
('garden-villa','Garden Villa','suite','A private villa with a plunge pool and a walled garden terrace.',120,4,2,'1 King and 1 Queen','Private garden',980,2,'["Plunge pool","Terrace","Private entrance","Butler"]','assets/img/rooms/garden-villa.jpg','[]',5),
('presidential-suite','Presidential Suite','suite','The top floor in full: two bedrooms, a library and a 360-degree terrace.',210,4,2,'2 Kings','360 skyline',1850,1,'["Terrace","Library","Private chef on request","Chauffeur"]','assets/img/rooms/presidential-suite.jpg','[]',6);

INSERT INTO addons (slug,name,category,description,price,pricing_type,savings_note,sort_order) VALUES
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
