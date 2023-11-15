# Plugin V�hicule Tesla pour Eedomus

Suivez les param�tres de votre v�hicule Tesla avec ce plugin pour [Eedomus](https://www.eedomus.com/).

Ce plugin a �t� d�velopp� par [mediacloud](https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280).

Voir la [discussion sur le forum](https://forum.eedomus.com/viewtopic.php?f=16&t=10515) pour plus d'informations ou donner un feedback.

La version actuelle est 1.9.0.

## Fonctionnalit�s

![tesla car overview](https://user-images.githubusercontent.com/94607717/145950751-715d7029-d674-4ff6-bfa9-946e81c59a48.png)

Ce plugin envoie les donn�es suivantes � Eedomus :

- localisation du v�hicule, latitude, longitude
- niveau de la batterie
- limite de recharge
- �nergie ajout�e (kWh, co�t)
- �tat de la trappe de la prise
- �tat de la recharge
- minutes de recharge restantes
- temps de recharge restant (h min)
- courant de recharge
- limite du courant de recharge
- tension de recharge
- puissance de recharge
- autonomie restante (th�orique)
- autonomie restante (estim�e)
- temp�rature ext�rieure
- temp�rature int�rieure
- �tat du chauffage et climatisation
- �tat du chauffage du si�ge gauche, droit, arri�re gauche, droit et central
- �tat du chauffage du volant
- compteur kilom�trique
- verrouillage du v�hicule
- nom du v�hicule
- levier de vitesse
- vitesse
- sentinelle
- �tat du d�marrage � distance
- �tat du coffre arri�re et avant

![tesla car actions](https://user-images.githubusercontent.com/94607717/145906384-73e170f4-7d2a-4093-a844-692092e90d8a.png)

Il expose les commandes suivantes (qui peuvent �tre utilis�es dans les r�gles) :

- r�veiller le v�hicule
- verrouiller/d�verrouiller le v�hicule
- faire clignoter l'�clairage ext�rieur
- activer le klaxon
- d�marrer/arr�ter la recharge
- ouvrir/fermer la trappe de la prise de recharge
- r�gler la limite de recharge (50%, 60%, 70%, 75%, 80%, 85%, 90%, 95%, 100%)
- r�gler le courant de recharge (5A, 8A, 10A, 13A, 16A, 20A, 24A, 28A, 32A)
- d�marrer/arr�ter le chauffage et la climatisation
- r�gler le chauffage des si�ges et du volant
- activer/d�sactiver Sentinelle
- d�marrage � distance
- ouverture des coffres arri�res et avant

Note : chaque commande r�veille le v�hicule si n�cessaire.

## Installation et configuration

Installez le plugin depuis le store Eedomus.

Il est recommand� d'avoir votre voiture Tesla r�veill�e lorsque vous installez le plugin. Pour r�veiller le v�hicule, lancez l'application Tesla sur votre t�l�phone, ou ouvrez/fermez une porti�re.

### Pi�ce

Cr�er une pi�ce 'Tesla' pour y affecter le v�hicule Tesla.

### Code et authentification

Le plugin r�cup�rera automatique le jeton d'acc�s en se connectant au serveurs Tesla. Il sera renouvel� automatiquement toutes les 8 heures.

Le plugin a besoin d'un code pour r�cup�rer le premier jeton. Pour obtenir le code :

- Cliquez sur le lien pour vous connecter avec votre compte Tesla

![auth url](https://user-images.githubusercontent.com/94607717/145906592-41b94333-5be6-4081-8184-0af5c1279c6d.png)

- Connectez-vous au site Tesla avec votre compte
- Une fois fait, une page "Page Not Found" sera affich�e. C'est normal. Regardez l'URL et r�cup�rer le param�tre **code** (texte apr�s `code=` et jusqu'au `&`)

![auth url](https://user-images.githubusercontent.com/94607717/144481395-b52b58f2-90b6-42c3-9f9a-4202525e1cca.png)

- Le code est valide 2 minutes. Copiez le dans la param�tre correspondant du plugin.

![code paste](https://user-images.githubusercontent.com/94607717/145906603-0cacb740-ed61-488f-b2bc-ffdfa3669656.png)

### VIN

Par d�faut, le plugin s�lectionne le premier v�hicule du compte. Vous pouvez s�lectionner un autre v�hicule de votre compte en fournissant son VIN.

### Cr�ation

Cliquez sur `Cr�er`.
Allez ensuite dans la pi�ce Tesla. Vous devriez voir les donn�es quelques secondes apr�s (si le v�hicule est r�veill�).

## Notes sur les intervalles de connexion et l'impact sur la batterie

Il y a des optimisations dans le plugin pour �viter de vider la batterie. Voici quelques d�tails :

- L'intervalle d'interrogation est de 1 � 3 minutes pour les compteurs mais il y a un cache de donn�es de 15 minutes dans le script pour permettre � la voiture de se mettre en veille. Les donn�es rapport�es peuvent donc avoir 15 min de retard, y compris pour les donn�es de g�olocalisation.
- Lorsque la voiture est endormie, les donn�es g�n�rales de la voiture et les donn�es GPS sont r�cup�r�es toutes les 15 minutes mais les donn�es seront vides (ou identiques) car la voiture est endormie. Il y a une exception pour l'**�tat de la voiture** qui utilise une API diff�rente : l'�tat est toujours mis � jour toutes les 3 minutes.
- Lorsque la voiture est active (la climatisation est en marche, la charge est en cours, la voiture n'est pas gar�e ou la sentinelle est activ�e), le suivi est effectu� toutes les 3 minutes. Si la voiture semble inactive pendant 10 minutes, la surveillance revient � toutes les 15 minutes pour que la voiture puisse s'endormir.

## Note sur le prix de l'�lectricit�

Vous pouvez modifier le prix du kWh dans la configuration du compteur "Energie ajout�e (co�t)". Mettez � jour la valeur dans l'expression XPATH.

![cost](https://user-images.githubusercontent.com/94607717/145906757-6c79004c-3f3a-4b1c-a600-6c80a0b37ce3.png)

## Note sur HomeKit/Siri

Le plugin a �t� test� avec Home Kit. Certains param�tres et commandes peuvent �tre pilot�s avec Siri. Apr�s configuration, il est possible d'utiliser les phrases suivantes :

- "Dis Siri, active le chauffage de la Tesla"
- "Dis Siri, active le d�marrage � distance de la Tesla"
- "Dis Siri, �teins le chauffage de la Tesla"
- "Dis Siri, quelle est la temp�rature int�rieure de la Tesla"
