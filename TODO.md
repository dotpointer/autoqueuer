--- FIXAS ---------------------------------------------------

- bug

  Date: Thu, 19 Oct 2017 07:20:16 +0200 (CEST)

  autoqueuer/worker.php --previewscan --preview

  Failed finding resolution at frame 0


- indexera downloads -1 korrekt:

- ta hem lista över transfers med ED2k-hashkoder

select * from files where id_collections=-1 and ed2k=hashen, sätt till existing=1;

- ta bort rootpath i collections om det inte används
- email


  include/functions.php

    # email
    ersätt så den lägger in targetpath i tabell i databasen, eller markera i databasen som maila-om-denna

  varje gång det körs något:


    Kolla om det finns filer som markerade som MAILSTATUS_SENT:

      'SELECT * FROM files WHERE mailstatus="'.MAILSTATUS_SENT.'"';

      Hittas NÅGON som har FLYTTATS eller TAGITS BORT, så markera ALLA MAILSTATUS_SENT och MAILSTATUS_NEW som MAILSTATUS_DONE.

    Hittas INGEN MAILSTATUS_SENT alls:

        kolla om det finns några filer i databasen som är markerade som MAILSTATUS_NEW, skicka då mail och markera som mailat om denna


    define('MAILSTATUS_NEW', 1); # this is an item that has not been notified of yet
    define('MAILSTATUS_SENT', 2);
    define('MAILSTATUS_DONE', 3);

    varje gång en fil flyttas, markera den som MAILSTATUS_NEW, så vida det inte redan finns en MAILSTATUS_NEW, markera då som MAILSTATUS_SENT.


- searches står det överallt, det borde stå scheduledsearches eller nåt

- autologout efter 3 min 
  = ställ om sessionen, så den bara håller 3 min, men fan va jobbigt, eller js-skript?
  = sätt en timer uppe till höger som räknar ner

- rensningsfunktion för möjliga dubletter
  - ta transfer-listan och kör den mot databasen - på filnamn utan ändelsen och i annan id_collection än download, ev. även trim + lowercase
  - lista potentiella fall



--- KLART / DUMPAT --------------------------------------------

- transfer-listan är trasig i mlnet

autoqueue - sortera transferlistan efter senaste uppdateringsdatum

emulehelper - visa när filen uppdaterades senast?
  hur då, fronten har inte nödvändigtvis tillgång till filerna i tempkatalogen, och fronten har bara tillgång till webbgränssnittet
  - fråga annan process med rättigheter
    - kreosot?
    - process med semafor-messagequeue?
      -- men detta gör att processen krävs, gör systemet ännu mer komplext
      -- ipcs -q
  - öppna rättigheter till tempkatalogen
  - låta annan process periodvis spara uppdateringsdata
  = indexera filerna i unfinished-katalogen

  en action scan_unfinished



  pump->previewScanUnfinished() <-- körs när mlnet:s unfinished-katalog förändrats

    hämta fillista på nuvarande filer i katalogen

    ta bort de i databasen som inte matchar på ed2k-namnet
      ska pump-klassen göra delete:n själv, eller ska en funktion göra det?

      function pump_delete_unfinished($id_pump, $array_ids)
        global $link
        DELETE FROm files_unfinished WHERE id_pumps=X AND id IN (...)

    uppdatera de i databasen där datum eller storlek förändrats - sätt renewable = 1
      ska pump-klassen göra det?

      function pump_update_unfinished($id_pump, $array_files
        global $link
        $array_files innehåller:
          name
          size
          modified

  previewGenerate()

    walka files_unfinished renewable = 1, kolla att filerna i fråga finns i katalogen, om inte, radera dem från tabellen och ta nästa

emulehelper - pumparna borde köra cl eller på nåt sätt kunna skriva ut meddelanden direkt


emulehelper - exportera ut progressbaren ingen bra idé, ett nytt anrop för varje filrad - men den gör redan det?
  emulehelper - skulle kunna byta ut ovanstående mot hämtning av tempdir, dock funkar det inte med alla filer då vissa inte är där

emulehelper - ja vad? namnbyte? = autoqueue bra

  autoloader, autodownloader, autoqueue
  pumpqueue

- __FILE__ och __LINE__ på alla fel.

- flytta filer och skriv in filens position i files-tabellen

  vad göra med transfers relation till det hela?

  ska den flytta filerna fortfarande, sen informera emh att reindexera? = onödig cpu-användning

  eller ska emh själv flytta och sätta rätt path / id_collection?
    -> id_collection_after_move
    -> path_after_move


- innan det går att slipa upp mlnet måste ramverket runtom bli stabilt.

- mellan-interface, som exponerar emule som en "universell" pump 

  search(text, options{min,max,media}) -> true/false

      s <query> :				search for files on all networks

        With special args:
        -network <netname>
          Får via kommandot networks
        -minsize <size>
        -maxsize <size>
        -media <Video|Audio|...>

          mlnet:

          Audio = Audio
          Video = Video
          Pro = Program
          Doc = Document
          Image = Image
          Col = Collection

          emulextreme:

          'Arc'	=> 'Archive',
          'Audio'	=> 'Audio',
          'Iso'	=> 'Disk image',
          'Doc'	=> 'Document',
          'Image'	=> 'Image',
          'Pro'	=> 'Program',
          'Video'	=> 'Video'

        -Video
        -Audio
        -format <format>
        -title <word in title>
        -album <word in album>
        -artist <word in artist>
        -field <field> <fieldvalue>
        -not <word>
        -and <word>
        -or <word>



  results() -> {name,size,ed2k,identifier}, 
  download(identifier) -> true/false
  ev. transfers()


- Läsa igenom workern samt indexeringen och kolla buggar och knasigheter

- resultscans, ska vara 2, under detta incrementera och sök filer

- filer i fel kategorier....

select * from files where id_searches=2 and id_collections = -1 and not name like '%word%';
 update files set id_searches=2 where id_collections=-1 and name like '%word%' and not id_searches=2;

-- 3 felrader, satt till 0

select * from files where id_searches=4 and id_collections = -1 and not name like '%word%';
 update files set id_searches=4 where id_collections=-1 and name like '%word%' and not id_searches=4;

-- 13 felrader, satt till 0

select * from files where id_searches=5 and id_collections = -1 and not name like '%word%';
update files set id_searches=5 where id_collections=-1 and name like '%word%' and not id_searches=5;

-- 32 felrader, satt till 0

select * from files where id_searches=6 and id_collections = -1 and not name like '%word%';
update files set id_searches=6 where id_collections=-1 and name like '%word%' and not id_searches=6;
-- 18 felrader, satt till 0

- kolla verbose så vi inte pajar nåt

- loggan är inte så snygg

- rsynca filerna

rsync --remove-source-files

rsync -avu 
  -a = behåll alla stats i princip (owner, group mmm...)
  -v = verbose
  -u = update destination files in place
  --delete = deletar target
  --remove-source-files = ta bort källfilerna efter sync
  --stats = visa stats
  --progress = visa progress


--update                skip files that are newer on the receiver


rsync -av --remove-source-files /public/dit/ /public/hit/

- vad göra om filerna redan finns på källan? kan ju vara dubletter av värde
- verkar som om vi måste fixa detta själv innan


- bara 1 instans, gör pidfil - nej, gör inte pidfil, för man ska ev. kunna köra flera olika grejer samtidigt, spara status i databasen

- gruppa flyttning av filer
  -- sök upp som vi gör nu i files, men ta inte ut moveto-fälten

  -- walka igenom och hämta ut id_searches, lägg fälten
  -- hämta hem alla sökningar som matchats
  -- koppla dessa mot filerna searches[] -> files[]

- gör testfiler för att testa filflyttning

- använd getopt() för att checka vad som ska göras



- gör en projekt-klass

- movetochmod
- movetochgrp

- gör om kommunikationen med projektet så man kan ha flera "pumpar"

  tabellförslag:

    clientpumps
      id
      nickname		eMule @ examplehost
      host			examplehost
      port			12345
      type			emulextreme, mldonkey-server
      username		http-username
      password		http-username

- koppla in emulextreme som pump

- koppla in mldonkey som pump

 - vad göra med flera klienter?
  - hur ska söken köras?
    - båda samtidigt?
    - slumpat? en utvald? varannan?
    - efter prioritet och tillgänglighet?
    - ska man välja en aktiv?

- kadcheck ska göras om till databasläge
  = var spara sessionskoden?

- när man sparar schemalagda sökningar får man error

- jslinta fronten

- Fatal error, could not escape hashes in downloaddir. Input data was: array ( ) - kanske fixad

- move- ska finnas en incoming-dir i clientpumps
