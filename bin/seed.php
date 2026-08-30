<?php

declare(strict_types=1);

use App\Domain\Entity\Category;
use App\Domain\Entity\Post;
use App\Domain\Entity\Tag;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use App\Domain\Value\Slug;
use Psr\Container\ContainerInterface;

/**
 * Development seed: 3 authors, PPL curriculum categories/tags and 30 posts
 * about aircraft and PPL lessons. Idempotent — re-running skips what exists.
 *
 * Local development only. It creates login-able accounts with a shared,
 * well-known password and (with --fresh) truncates tables, so it refuses to
 * run against anything that does not look like the local Docker database.
 *
 *   php bin/seed.php            (or: make seed)
 *   php bin/seed.php --fresh    wipe the blog tables first (or: make seed-fresh)
 */

chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require 'config/container.php';

$db   = $container->get(PhpDb\Adapter\AdapterInterface::class);
$host = (string) ($db->getDriver()->getConnection()->getConnectionParameters()['host'] ?? '');

if (! in_array($host, ['mariadb', 'localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Refusing to seed: database host '{$host}' is not a recognised local/Docker host.\n");
    exit(1);
}

$users      = $container->get(UserRepositoryInterface::class);
$categories = $container->get(CategoryRepositoryInterface::class);
$tags       = $container->get(TagRepositoryInterface::class);
$posts      = $container->get(PostRepositoryInterface::class);

if (in_array('--fresh', $argv, true)) {
    $db->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['post_tag', 'posts', 'tags', 'categories', 'users'] as $table) {
        $db->executeQuery("TRUNCATE TABLE `{$table}`");
    }
    $db->executeQuery('SET FOREIGN_KEY_CHECKS = 1');
    echo "! wiped blog tables\n";
}

// ---------------------------------------------------------------------------
// Authors (roles TBD)
// ---------------------------------------------------------------------------

// Dev-only shared password for the sample accounts; override with SEED_PASSWORD.
$passwordHash = password_hash(getenv('SEED_PASSWORD') ?: 'password', PASSWORD_BCRYPT);

$authorDefs = [
    ['Julien', 'julien@guittard.me'],
    ['Nadia Fournier', 'nadia@guittard.me'],
    ['Ben Carver', 'ben@guittard.me'],
];

$authorIds = [];
foreach ($authorDefs as [$name, $email]) {
    $existing = $users->findByEmail(Email::fromString($email));
    $user     = $existing ?? $users->save(User::register(Email::fromString($email), $name, $passwordHash));

    $authorIds[] = $user->id;
    printf("%s author  %-16s <%s>  %s\n", $existing ? '=' : '+', $name, $email, $user->id);
}

// ---------------------------------------------------------------------------
// Categories (PPL theory subjects)
// ---------------------------------------------------------------------------

$categoryDefs = [
    'Principles of Flight'       => 'How wings, thrust and control surfaces actually work.',
    'Aircraft General Knowledge' => 'Engines, systems, instruments and the machines we train on.',
    'Air Law'                    => 'Rules, airspace and the paperwork behind every flight.',
    'Meteorology'                => 'Reading the sky, the charts and the forecasts.',
    'Navigation'                 => 'Getting from A to B with map, compass and clock.',
    'Human Performance'          => 'The pilot as a component: physiology and decision-making.',
    'Flight Operations'          => 'Checklists, circuits, procedures and airmanship.',
    'Radio Communications'       => 'Talking to ATC without freezing up.',
];

$categoryIds = [];
foreach ($categoryDefs as $name => $description) {
    $existing = $categories->findBySlug(Slug::fromTitle($name));
    $category = $existing ?? $categories->save(Category::create($name, $description));

    $categoryIds[$name] = $category->id;
    printf("%s category %-28s %s\n", $existing ? '=' : '+', $name, $category->id);
}

// ---------------------------------------------------------------------------
// Posts
// ---------------------------------------------------------------------------

/** @var list<array{0:string,1:string,2:list<string>,3:string,4:string}> $postDefs */
$postDefs = [
    [
        'The Four Forces, Demystified',
        'Principles of Flight',
        ['four forces', 'lift', 'drag'],
        'Lift, weight, thrust and drag are always in a conversation. Learn what it means when they are in balance.',
        <<<BODY
        Every aeroplane in flight is dealing with four forces: lift opposing weight, thrust opposing drag. In unaccelerated straight-and-level flight they cancel out in pairs, and the aircraft neither climbs, descends nor changes speed.

        The moment you change one, you change the others. Add power and thrust exceeds drag, so the aircraft accelerates until drag rises to match it again. Raise the nose and you trade airspeed for a higher angle of attack, momentarily increasing lift and starting a climb, until the new equilibrium settles.

        This is why a light aircraft is trimmed for an attitude, not a speed, and why "pitch plus power equals performance" is the phrase your instructor keeps repeating. Understand the four-force balance and every later lesson, from the climb to the glide approach, becomes a variation on the same theme.
        BODY,
    ],
    [
        'Why Your Wing Actually Flies',
        'Principles of Flight',
        ['lift', 'aerofoil', 'angle of attack'],
        'Forget the equal-transit-time story. A wing makes lift by turning air downward, and the numbers back it up.',
        <<<BODY
        The popular explanation, that air over the curved top has "further to travel" and therefore speeds up, is wrong: the parcels never meet again, and flat plates fly perfectly well. What a wing really does is deflect a large mass of air downwards. By Newton's third law, the air pushes the wing up.

        The pressure picture is the same story told differently. Curving the flow over the upper surface lowers the pressure there; the higher pressure underneath does the rest. Both descriptions are valid, and both depend on angle of attack and airspeed.

        The practical takeaway for a student pilot: lift is something you fly, not something the aeroplane simply "has". Change the angle of attack or the speed and you change the lift immediately, which is exactly what you are doing every time you flare, climb or turn.
        BODY,
    ],
    [
        'Angle of Attack Is the Only Number That Matters',
        'Principles of Flight',
        ['angle of attack', 'stall', 'slow flight'],
        'A wing stalls at an angle, not a speed. Once that clicks, stall recovery stops being scary.',
        <<<BODY
        Angle of attack is the angle between the wing chord line and the oncoming air. A wing always stalls at the same critical angle of attack, roughly 15 to 16 degrees on a typical trainer, regardless of weight, bank angle or airspeed.

        That is why the stall speed in your POH is quoted for one specific configuration and load. Pull harder in a turn and you reach the critical angle at a higher speed; that is the accelerated stall. Load the aircraft heavier and the same thing happens.

        Recovery is therefore always the same action: reduce the angle of attack by lowering the nose, then add power and level the wings. You are not "getting speed back" first, you are un-stalling the wing first. Practise it until the response is automatic, because in a real event you will not have spare seconds to think.
        BODY,
    ],
    [
        'Adverse Yaw and the Rudder You Forget',
        'Principles of Flight',
        ['adverse yaw', 'rudder', 'coordination'],
        'The nose swings the wrong way when you roll. Here is why, and how to fix it with your feet.',
        <<<BODY
        Roll into a turn with aileron alone and the nose initially yaws away from the turn. The down-going aileron on the outside wing creates more lift and, crucially, more induced drag than the up-going one, dragging that wing back.

        The cure is rudder applied in the same direction as the roll, timed with the aileron input. Too little and the aircraft skids with the ball out; too much and it slips with the ball in. Your instructor will have you chase the balance ball or a piece of wool on the windscreen until coordinated turns feel natural.

        Coordination matters beyond tidiness. An uncoordinated stall, particularly a skidding one in the base-to-final turn, is how a benign wing drop becomes an incipient spin close to the ground.
        BODY,
    ],
    [
        'Meet the Cessna 172',
        'Aircraft General Knowledge',
        ['Cessna 172', 'preflight', 'instruments'],
        'The Skyhawk has trained more pilots than any other aircraft. A quick tour of your likely first classroom.',
        <<<BODY
        The Cessna 172 Skyhawk is a high-wing, strut-braced, four-seat single with a fixed tricycle undercarriage and, in most training examples, a 160-horsepower Lycoming engine driving a fixed-pitch propeller.

        Students like it because the high wing gives good downward visibility and shade on the ramp, the aircraft is naturally stable, and the systems are simple: gravity-fed fuel from two wing tanks, a vacuum or electric attitude reference, and a 28-volt or 14-volt electrical system depending on the vintage.

        Its manners are forgiving. The stall is preceded by a clear buffet and an audible warning, spins are recoverable with standard technique, and a well-flown short-field landing needs only a few hundred metres. Know its four fuel drains, its flap limiting speeds and its useful load, and the 172 will look after you.
        BODY,
    ],
    [
        'The PA-28 Cherokee: A Low-Wing Alternative',
        'Aircraft General Knowledge',
        ['PA-28', 'Cessna 172', 'handling'],
        'If your school flies Pipers instead of Cessnas, here is what changes and what does not.',
        <<<BODY
        The Piper PA-28 family, Cherokee, Warrior and Archer, fills the same training role as the Cessna 172 but with a low wing. The differences are mostly ergonomic. You board over the wing through a single right-hand door, visibility in a turn favours looking down into the turn, and the fuel selector demands a deliberate left-right-off discipline because tanks do not feed simultaneously.

        In the flare, the low wing sits closer to the runway and benefits from more pronounced ground effect, so a Piper rewards a patient, gradual round-out. The stall is gentle and the controls are a little heavier and less crisp than a Cessna's.

        Neither layout is better for learning. Pick the school and the instructor first; the wing position is something you adapt to within a few hours.
        BODY,
    ],
    [
        'Carburettor Icing: The Silent Power Thief',
        'Aircraft General Knowledge',
        ['carb icing', 'engine', 'checklist'],
        'It can form on a warm day with the throttle back, and the first symptom is a quiet loss of power.',
        <<<BODY
        A carburettor cools sharply as fuel vaporises and pressure drops in the venturi. In humid air, moisture then freezes onto the throttle butterfly and progressively chokes the induction system. The classic conditions are not freezing at all: think 10 to 20 degrees Celsius with visible damp or high humidity.

        In a fixed-pitch aircraft the first sign is a slow, unexplained drop in RPM, sometimes with rough running, most likely during a glide descent with the throttle closed. Applying full carburettor heat initially makes things worse as the melting ice passes through the engine, then the power recovers.

        Use carb heat as briefed: as a check before the descent, throughout a prolonged glide, and any time performance is unexplained. It costs a little power and a slightly richer mixture, and it is cheap insurance.
        BODY,
    ],
    [
        'Reading the Six-Pack',
        'Aircraft General Knowledge',
        ['instruments', 'six pack', 'scan'],
        'Six instruments, two power sources, one scan pattern. Build the habit early.',
        <<<BODY
        The traditional analogue panel groups six instruments in two rows. Top row: airspeed indicator, attitude indicator, altimeter. Bottom row: turn coordinator, heading indicator, vertical speed indicator. The attitude and heading indicators are usually vacuum-driven; the rest are pitot-static or electric.

        Knowing the power source matters because failures are insidious. A dying vacuum pump lets the attitude indicator lean and the heading drift slowly, tempting you to follow it into a spiral. Cross-checking against the turn coordinator, altimeter and compass catches it.

        Your visual scan should radiate from the attitude indicator out to whichever instrument is relevant to the current task, then back to the centre. In VFR flight, most of your attention stays outside; the panel is a quick confirmation, not a video game.
        BODY,
    ],
    [
        'VFR Minimums Without the Headache',
        'Air Law',
        ['VFR', 'airspace', 'weather'],
        'Visibility and distance from cloud rules change with airspace and altitude. A working summary.',
        <<<BODY
        Visual flight rules exist so you can see and avoid other traffic and stay clear of cloud. The exact figures depend on your airspace class and altitude, but the shape is consistent: more room is required higher up and in busier airspace.

        A common lower-airspace baseline is 5 kilometres flight visibility, 1500 metres horizontally from cloud and 1000 feet vertically. Below 3000 feet in some classes you may operate "clear of cloud, in sight of the surface" with reduced visibility, which is a trap in marginal conditions.

        Learn your own country's table, then apply a personal minimum well above it while you build experience. The legal number is where enforcement starts; it is not a recommendation.
        BODY,
    ],
    [
        'Controlled and Uncontrolled Airspace, Explained',
        'Air Law',
        ['airspace', 'ATC', 'clearance'],
        'Classes A to G in plain language, and what each one asks of a VFR pilot.',
        <<<BODY
        Airspace is sliced into classes A through G. The lettered classes A to E are "controlled": an air traffic service provides separation to at least some traffic. Classes F and G are "uncontrolled", where you get information and a listening service at best and separation is your own responsibility.

        For a VFR student the practical distinctions are: Class A is IFR only and off-limits; Class C and D require a clearance to enter and two-way communication; Class E is controlled for IFR but VFR may enter without a clearance; Class G is where most training happens.

        The lesson is to read the chart before you fly, identify every piece of airspace along the route, and know in advance which needs a phone call, a clearance or simply a good lookout.
        BODY,
    ],
    [
        'Who Has Right of Way?',
        'Air Law',
        ['right of way', 'collision avoidance', 'circuit'],
        'Converging, head-on, overtaking, and the pecking order of aircraft types.',
        <<<BODY
        The rules of the air borrow heavily from seamanship. Converging at a similar height, the aircraft with the other on its right gives way. Head-on, both turn right. Overtaking, you pass on the right and keep clear until well ahead.

        There is also a hierarchy by manoeuvrability and commitment: a balloon has right of way over a glider, a glider over an airship, and an aircraft on final approach or landing has right of way over one still manoeuvring. An aircraft in distress trumps everything.

        None of this replaces a constant lookout. Right of way tells you who should do what; it does not guarantee the other pilot has seen you, so be prepared to give way even when the rules say you need not.
        BODY,
    ],
    [
        'The Documents That Must Be On Board',
        'Air Law',
        ['documents', 'preflight', 'airworthiness'],
        'A memory aid for the paperwork that makes a flight legal, plus what you personally must carry.',
        <<<BODY
        Before the wheels leave the ground, a specific set of documents must be in the aircraft. A common mnemonic is ARROW: Airworthiness certificate, Registration certificate, Radio licence where required, Operating limitations (the POH or flight manual and placards), and Weight and balance data.

        You, the pilot, must also carry your licence, a valid medical certificate and photographic identification, and for many operations a means of logging the flight.

        Checking this is part of the preflight, not an afterthought. An expired registration or a missing weight and balance schedule grounds the aircraft just as surely as a flat tyre, and it is the sort of thing a ramp check finds instantly.
        BODY,
    ],
    [
        'Decoding a METAR in Under a Minute',
        'Meteorology',
        ['METAR', 'weather', 'preflight'],
        'Wind, visibility, weather, cloud, temperature, pressure. Read it in that order and it falls out.',
        <<<BODY
        A METAR is an actual weather report in a fixed order. After the station identifier and time, you get the wind (direction in degrees true and speed, with gusts after a G), then visibility, then any significant weather such as RA for rain or BR for mist.

        Cloud follows as amount and base: FEW, SCT, BKN or OVC at a height in hundreds of feet. Then temperature and dew point separated by a slash, and finally the QNH, the pressure setting that makes your altimeter read altitude above sea level.

        Practise on your home airfield every day for a week and the code stops being code. Pay special attention to the temperature and dew point spread: when they close to within a couple of degrees, expect mist, fog or low cloud.
        BODY,
    ],
    [
        'TAFs and How Far to Trust Them',
        'Meteorology',
        ['TAF', 'forecast', 'flight planning'],
        'A forecast is a professional opinion with a validity window and change groups. Read the caveats.',
        <<<BODY
        A TAF is a forecast for a small area around an airfield, valid for a stated period. The base conditions are given like a METAR, then amended by change groups: BECMG for a gradual change, TEMPO for temporary fluctuations expected to last less than an hour at a time, and PROB for a percentage chance of the stated conditions.

        The skill is reading the change groups against your timing. A TEMPO 3000 SHRA sitting over your planned arrival is a reason to carry more fuel and a firm alternate, even if the base forecast looks fine.

        Trust a TAF for trend and structure, not for precise timing. Cross-check it against the actual METARs as they arrive and against the bigger picture on the area forecast.
        BODY,
    ],
    [
        'Density Altitude: When the Air Gets Thin',
        'Meteorology',
        ['density altitude', 'performance', 'takeoff'],
        'Hot, high and humid all rob performance. The runway that was fine in winter may not be in July.',
        <<<BODY
        Density altitude is pressure altitude corrected for temperature: it is the altitude the air "feels like" to your wing, propeller and engine. On a hot day at a high-elevation field, the density altitude can be thousands of feet above the airfield elevation.

        The engine makes less power because it ingests less oxygen, the propeller bites less air, and the wing needs a higher true airspeed for the same lift. The result is a longer take-off run, a shallower climb and a longer landing roll, all at once.

        Before a summer departure from an unfamiliar strip, compute it and then work the performance charts for the actual conditions. If the numbers are marginal, wait for the cool of the morning or offload weight.
        BODY,
    ],
    [
        'Reading the Wind Before You Fly',
        'Meteorology',
        ['wind', 'crosswind', 'circuit'],
        'Gradient wind, surface wind, gusts and local effects, and how each one reaches you in the circuit.',
        <<<BODY
        The wind you plan with, from the forecast or the METAR, is usually the mean surface wind. The gradient wind a few thousand feet up is stronger and backed or veered relative to the surface because friction no longer slows and turns it.

        Near the ground, expect gusts, mechanical turbulence downwind of hangars and trees, and local effects like a sea breeze that builds through the afternoon or katabatic flow off high ground in the evening.

        In the circuit this shows up as a changing crosswind component leg by leg, a floating or dropping approach as you descend through wind shear, and a groundspeed on final that is not what you expected. Brief the runway, the crosswind limit and your go-around trigger before you join.
        BODY,
    ],
    [
        'Dead Reckoning Still Works',
        'Navigation',
        ['dead reckoning', 'flight planning', 'cross-country'],
        'Heading, speed and time got pilots across oceans. It will get you to the next airfield.',
        <<<BODY
        Dead reckoning is navigation by calculation: from a known position, apply a heading and a groundspeed for a measured time and you arrive at a computed position. Add wind to your true airspeed and desired track to get the heading to steer, then work out the groundspeed and the leg time.

        A whiz-wheel or an electronic flight computer does the triangle of velocities for you. The discipline is to fly the heading accurately, hold the altitude and airspeed you planned for, and start the stopwatch overhead the departure point.

        GPS has not made this obsolete. Dead reckoning is what tells you the GPS is lying, and it is the plan you fall back on when the screen goes dark.
        BODY,
    ],
    [
        'Planning Your First Cross-Country',
        'Navigation',
        ['cross-country', 'flight planning', 'diversion'],
        'Route, weather, fuel, weight, NOTAMs and a plog. The night before matters more than the flight.',
        <<<BODY
        A first navigation exercise is mostly preparation. Draw the route on the chart avoiding controlled airspace and danger areas, pick landmarks every ten minutes or so, and measure the track and distance for each leg.

        Then layer on the weather: a wind and temperature forecast to compute headings and groundspeeds, and an actual and forecast check for departure, destination and alternate. Do the fuel plan with a fixed reserve you will not touch, and confirm the aircraft is within weight and balance with that fuel and your passengers.

        Read the NOTAMs, brief the diversion options in advance, and build the plog so that in the air you are reading and confirming, not calculating. The workload in the cockpit is high enough without arithmetic.
        BODY,
    ],
    [
        'The 1-in-60 Rule for Track Corrections',
        'Navigation',
        ['1-in-60', 'dead reckoning', 'mental maths'],
        'One degree of error opens up one mile of drift over sixty. The mental sum that keeps you on track.',
        <<<BODY
        The 1-in-60 rule is a small-angle approximation: at 60 nautical miles, one nautical mile off track corresponds to roughly one degree of angular error. It is close enough for cockpit mental arithmetic.

        Suppose you have flown 30 miles of a leg and you fix your position 3 miles right of track. That is a 6-degree tracking error, so turn 6 degrees left to parallel the desired track. To actually close back onto track before the end of the leg, add a further correction for the distance remaining.

        Practise it on the ground with paper examples until the numbers are instant. In the air you want to glance out, identify the feature, and apply the correction in one smooth adjustment.
        BODY,
    ],
    [
        'Using the Magnetic Compass and Its Lies',
        'Navigation',
        ['magnetic compass', 'turning errors', 'instruments'],
        'It is the only heading reference with no moving parts to fail, and it misbehaves predictably.',
        <<<BODY
        The magnetic compass is simple and reliable, but it only reads correctly in steady, level, unaccelerated flight. In a turn through north it lags, so you roll out early; through south it leads, so you roll out late. A useful memory aid in the northern hemisphere is UNOS: Undershoot North, Overshoot South.

        It also shows acceleration errors on east or west headings: accelerating swings the reading toward north, decelerating toward south, remembered as ANDS.

        Because of all this, you normally steer by the heading indicator and reset it against the compass every ten or fifteen minutes in straight and level flight. If the vacuum system fails, the compass becomes primary, and knowing its quirks is what lets you fly an accurate heading without one.
        BODY,
    ],
    [
        'Hypoxia at Altitudes You Did Not Expect',
        'Human Performance',
        ['hypoxia', 'physiology', 'night flying'],
        'You do not have to be high to be short of oxygen. The insidious part is that you feel fine.',
        <<<BODY
        Hypoxic hypoxia, too little oxygen reaching the blood, begins to degrade performance from a few thousand feet, well before any regulatory oxygen requirement. Night vision suffers first, then judgement, then fine motor skill, and the victim characteristically feels euphoric and capable while getting worse.

        Contributing factors stack up: a smoker starts with a carbon monoxide handicap equivalent to a few thousand feet of altitude; a poor night's sleep, mild dehydration or a heavy cold all reduce your reserve.

        The defences are simple. Know your personal ceiling, use supplemental oxygen earlier than the minimum when flying at night or when tired, and treat any unexplained clumsiness or tunnel vision as hypoxia until proven otherwise: descend.
        BODY,
    ],
    [
        'The IMSAFE Check Before Every Flight',
        'Human Performance',
        ['IMSAFE', 'fitness to fly', 'decision making'],
        'A thirty-second self-brief that has kept a lot of pilots on the ground for the right reasons.',
        <<<BODY
        IMSAFE is a checklist for the pilot rather than the aircraft. Illness: even a minor cold affects the ears and sinuses and dulls thinking. Medication: many common remedies are disqualifying or sedating. Stress: a bad week narrows your attention. Alcohol: observe the legal bottle-to-throttle time and remember the hangover outlasts the breath test. Fatigue: the most under-reported hazard in general aviation. Eating and hydration: low blood sugar and dehydration both slow you down.

        Run it honestly on the drive to the airfield, not while strapping in. The value of the checklist is that it gives you permission, and a structure, to say "not today" before you have sunk cost into the flight.
        BODY,
    ],
    [
        'Get-There-Itis and How to Beat It',
        'Human Performance',
        ['decision making', 'pressure', 'diversion'],
        'The urge to press on is a known killer. Build the off-ramps into the plan before you need them.',
        <<<BODY
        Plan-continuation bias, known in the hangar as get-there-itis, is the tendency to stick with a plan even as the evidence against it piles up. It is strongest when there is a meeting, a booking or a passenger expectation waiting at the destination.

        The antidote is to make the decision before the pressure arrives. Set hard limits during planning: a minimum cloud base and visibility for the route, a latest acceptable arrival time, a fuel state that triggers a diversion. Write them on the plog.

        Then, in the air, treat a limit being reached as a decision already made, not a fresh negotiation. Divert, hold or turn back while you still have the fuel, daylight and options to do it comfortably.
        BODY,
    ],
    [
        'Flying a Tidy Circuit',
        'Flight Operations',
        ['circuit', 'checklist', 'airmanship'],
        'The circuit is where most of your early hours are spent. Precision here pays off everywhere.',
        <<<BODY
        A standard circuit is a rectangle: upwind, crosswind, downwind, base and final, usually flown left-hand at a defined height, commonly 1000 feet above the airfield. Each leg has a job. Crosswind: climb and turn on time. Downwind: level off, set cruise power, run the pre-landing checks and make your radio call abeam the threshold.

        Base is where it comes together: reduce power, first stage of flap, start the descent, and turn final aiming to roll out on the extended centreline, not chasing back to it.

        Consistency is the goal. If every downwind is the same height, speed and distance from the runway, then every approach starts from the same place, and your landings improve because the variable you are practising is just the last hundred feet.
        BODY,
    ],
    [
        'The Stabilised Approach, and When to Go Around',
        'Flight Operations',
        ['stabilised approach', 'go-around', 'landing'],
        'On speed, on slope, on centreline, configured and trimmed by a gate height, or you go around.',
        <<<BODY
        A stabilised approach means that by a defined gate, often 300 to 500 feet on final for a light aircraft, you are on the correct approach path, on the target airspeed within a few knots, in the landing configuration, correctly trimmed and with only small corrections needed.

        If you are not, the correct response is a go-around, and it should be a non-event: full power, control the pitch to the climb attitude, positive rate, flaps retracted in stages, and climb straight ahead to circuit height before repositioning.

        The reason instructors are strict about this is that unstable approaches are strongly associated with runway excursions and hard landings. Deciding your gate criteria on the ground removes the temptation to salvage a bad approach in the flare.
        BODY,
    ],
    [
        'Crosswind Landings: Crab, Kick, Hold',
        'Flight Operations',
        ['crosswind landing', 'wind', 'rudder'],
        'Two techniques, one goal: touch down on the centreline with the wheels pointing down the runway.',
        <<<BODY
        On a crosswind approach you can crab, pointing the nose into wind so the track stays down the centreline, or use the wing-down sideslip, lowering the into-wind wing and holding straight with opposite rudder. Many pilots crab down the approach and transition to wing-down in the last few seconds.

        The classic sequence is crab, then in the round-out kick the rudder to align the fuselage with the runway, then hold the into-wind aileron to stop the aircraft drifting. Touch down on the upwind main wheel first, then the other, then the nose.

        After touchdown the job is not done: keep flying the aeroplane, hold aileron into wind, and use rudder to track the centreline as the aerodynamic controls become less effective.
        BODY,
    ],
    [
        'Your First Solo: What Actually Happens',
        'Flight Operations',
        ['first solo', 'circuit', 'milestone'],
        'One or two circuits, a lighter aircraft, and an instructor who suddenly is not there. It goes quickly.',
        <<<BODY
        The first solo usually comes after a run of consistent circuits where the instructor has been quiet. They will get out, often after a full-stop landing, sign the paperwork, and send you for one circuit.

        Two things surprise everyone. The aircraft climbs noticeably better and floats a little more in the flare without the instructor's weight, so expect the numbers to shift. And the cockpit is very quiet, which is the point: there is no one to prompt the checks or the radio calls, so you talk to yourself and work methodically.

        Fly it exactly like the last dual circuit. Normal downwind, normal checks, normal approach speed. Land it, taxi in, and enjoy the fact that from now on the logbook has your name in the P1 column.
        BODY,
    ],
    [
        'Weight and Balance for the Cessna 172',
        'Flight Operations',
        ['weight and balance', 'Cessna 172', 'loading'],
        'Within limits is not enough. The centre of gravity has to be in the envelope too, loaded and empty of fuel.',
        <<<BODY
        A weight and balance calculation has two questions: is the aircraft below its maximum take-off weight, and is the centre of gravity within the forward and aft limits for that weight?

        Work it with moments. Multiply each mass, empty aircraft, front seats, rear seats, baggage, fuel, by its arm from the datum to get a moment, sum the masses and the moments, and divide to get the centre of gravity position. Plot it on the envelope in the POH.

        Two traps on the 172: baggage loaded aft with two adults in the back can push the centre of gravity behind the limit, and because fuel sits near the datum, an aircraft that is in limits full can move as it burns off. Check both the take-off and the landing case.
        BODY,
    ],
    [
        'Your First Radio Call Without the Panic',
        'Radio Communications',
        ['radio', 'phraseology', 'first solo'],
        'Who you are calling, who you are, where you are, what you want. Write it, read it, then say it.',
        <<<BODY
        Radio freezes students because it feels like a performance. It is not. Every initial call has the same four parts: the station you are calling, your callsign, your position and altitude, and your request or intention.

        For the first few flights, write the call on your kneeboard before you key the microphone. Listen for a gap, press, speak at a walking pace, release. If you fluff it, "say again" and "correction" exist for exactly that. Controllers would far rather you were slow and clear than fast and garbled.

        Read back anything that is a clearance, a runway, a frequency or an altimeter setting. Everything else you can simply acknowledge with your callsign. Within a dozen flights it becomes routine.
        BODY,
    ],
    [
        'Standard Phraseology That Keeps You Sane',
        'Radio Communications',
        ['phraseology', 'radio', 'ATC'],
        'The scripted words exist so that a tired pilot and a busy controller cannot misunderstand each other.',
        <<<BODY
        Standard phraseology is deliberately narrow. "Roger" means I have received your last transmission, nothing more. "Wilco" means I will comply. "Affirm" and "negative" replace an ambiguous yes and no. Numbers are spoken digit by digit, with the altimeter and headings given as three digits.

        "Say again" asks for a repeat; "read back" asks you to confirm the exact words; "correction" precedes a fix to something you just said. A clearance is read back in full; an instruction is acknowledged.

        The point is not etiquette, it is safety. When both pilot and controller stick to the script, a weak signal, an accent or a moment of distraction is far less likely to turn into a runway incursion or a level bust.
        BODY,
    ],
];

$publishFrom = new DateTimeImmutable('2026-08-28 08:00:00');
$total       = count($postDefs);
$created     = 0;

foreach ($postDefs as $index => [$title, $categoryName, $tagNames, $excerpt, $body]) {
    if ($posts->findBySlug(Slug::fromTitle($title)) !== null) {
        printf("= post   %s\n", $title);
        continue;
    }

    $authorId    = $authorIds[$index % 3];
    $categoryId  = $categoryIds[$categoryName];
    $publishedAt = $publishFrom->modify('-' . (($total - 1 - $index) * 9 + 2) . ' days');

    $tagIds = array_map(
        static fn (Tag $tag): string => $tag->id,
        $tags->findOrCreateByNames($tagNames),
    );

    $post = Post::draft($authorId, $title, trimBody($body), $categoryId, $excerpt)->publish($publishedAt);
    $saved = $posts->save($post, $tagIds);
    $created++;

    printf(
        "+ post   %s [%s] %s  (author %s, %d tags, %s)\n",
        $saved->id,
        $categoryName,
        $title,
        $authorId,
        count($tagIds),
        $publishedAt->format('Y-m-d'),
    );
}

printf("\nSeed complete: %d authors, %d categories, %d/%d posts created.\n", count($authorIds), count($categoryIds), $created, $total);

exit(0);

/** Collapse the heredoc's leading indentation into clean paragraphs. */
function trimBody(string $body): string
{
    $lines = array_map('trim', explode("\n", $body));

    return trim(implode("\n", $lines));
}
