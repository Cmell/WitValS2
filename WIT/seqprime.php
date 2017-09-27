<?php
require_once('../Resources/Util.php');
require_once('../Resources/pInfo.php');
session_start();
?>

<!doctype html>
<html>
<head>
  <title>Experiment</title>
  <meta http-equiv="cache-control" content="public | private">
  <meta http-equiv="content-type" content="text/html" charset="utf-8">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/seedrandom/2.4.2/seedrandom.min.js"></script>
	<script src="../Resources/jspsych-5.0.3/jspsych.js"></script>
  <script src="../Resources/jspsych-5.0.3/plugins/jspsych-sequential-priming.js"></script>
  <script src="../Resources/jspsych-5.0.3/plugins/jspsych-text.js"></script>
  <script src="../Resources/jspsych-5.0.3/plugins/jspsych-call-function.js"></script>
  <script src="https://cdn.rawgit.com/Cmell/JavascriptUtilsV9-20-2017/master/Util.js"></script>
  <script src='../Resources/ModS3JSUtil.js'></script>
	<link href="../Resources/jspsych-5.0.3/css/jspsych.css" rel="stylesheet" type="text/css"></link>
</head>
<body>

</body>
<script>
  // Define vars
  var seed, pid, taskTimeline;
  var leftKeyCode, rightKeyCode, correct_answer, goodKeyCode, badKeyCode;
  var mask, redX, check, expPrompt;
  var instr1, instructStim, countdown, countdownNumbers;
  var timeline = [];
  var numTrials = 320;
  var timing_parameters = [200, 200, 200, 500];
  var primeSize = [240, 336];
  var targetSize = [380, 380];
  var minorityProp = .25; var majorityProp = 1 - minorityProp;

  // The timing_parameters should correspond to the planned set of stimuli.
  // In this case, I'm leading with a mask (following Ito et al.), and then
  // the prime, and then the stimulus, and then the mask until the end of the
  // trial.

  // get the pid, read keys:
  <?php
  // Get the pid:
  $pid = getNewPID('../Resources/PID.csv');
  // put the variable out
  echo "pid = ".$pid.";";
  ?>

  d = new Date();
  seed = d.getTime();
  Math.seedrandom(seed);

  // Some utility variables
  var pidStr = "00" + pid; pidStr = pidStr.substr(pidStr.length - 3);// lead 0s

  var flPrefix = '../Resources/data/witValS2';

  var filename = flPrefix + "_" + pidStr + "_" + seed + ".csv";

  var fields = [
    "pid",
    "gun_key",
    "nogun_key",
    "internal_node_id",
    "key_press",
    "left_valence",
    "right_valence",
    "seed",
    "trial_index",
    "trial_type",
    "trial_num",
    "object",
    "object_id",
    "prime",
    "prime_id",
    "rt",
    "time_elapsed",
    "rt_from_start",
    "correct"
  ]

  // Image files for the task
  mask = "MaskReal.png";
  redX = "XwithSpacebarMsg.png";
  check = "CheckReal.png";
  tooSlow = "TooSlow.png";
  blank = "Blank.png"

  // Choose keys:
  gunKey = rndSelect(["e", "i"], 1);
  nogunKey = gunKey == "e" ? "i" : "e";
  leftKey = "e";
  rightKey = "i";
  leftKeyCode = jsPsych.pluginAPI.convertKeyCharacterToKeyCode(leftKey);
  rightKeyCode = jsPsych.pluginAPI.convertKeyCharacterToKeyCode(rightKey);
  gunKeyCode = jsPsych.pluginAPI.convertKeyCharacterToKeyCode(gunKey);
  nogunKeyCode = jsPsych.pluginAPI.convertKeyCharacterToKeyCode(nogunKey);
  leftObj = gunKeyCode == leftKeyCode ? "GUN" : "NON-GUN";
  rightObj = gunKeyCode == rightKeyCode ? "GUN" : "NON-GUN";

  if ((leftObj != "GUN" && leftObj != "NON-GUN") || (rightObj != "GUN" && rightObj != "NON-GUN")) {
    throw "keys are bad!";
  }

  // Append pid and condition information to all trials, including my
  // trialNum tracking variable (dynamically updated).
  jsPsych.data.addProperties({
    pid: pid,
    seed: seed,
    gun_key: gunKey,
    nogun_key: nogunKey,
    left_object: leftObj,
    right_object: rightObj
  });

  // utility sum function
  var sum = function (a, b) {
    return a + b;
  };

  // Save data function
  var saveAllData = function () {
    var filedata = jsPsych.data.dataAsCSV;
    // send it!
  	sendData(filedata, filename);
  };

  var endTrial = function (trialObj) {
    // Extract trial information from the trial object adding data to the trial
    var trialCSV = trialObjToCSV(trialObj);
    sendData(trialCSV, filename);
  };

  var generateHeader = function () {
    var line = '';
    var f;
    var fL = fields.length;
    for (i=0; i < fL; i++) {
      f = fields[i];
      if (i < fL - 1) {
        line += f + ',';
      } else {
        // don't include the comma on the last one.
        line += f;
      }
    }

    // Add an eol character or two
    line += '\r\n';
    return(line);
  };

  var sendHeader = function () {
    sendData(generateHeader(), filename);
  }

  var trialObjToCSV = function (t, extras) {
    // t is the trial object
    var f;
    var line = '';
    var fL = fields.length;
    var thing;

    for (i=0; i < fL; i++) {
      f = fields[i];
      thing = typeof t[f] === 'undefined' ? 'NA' : t[f];
      if (i < fL - 1) {
        line += thing + ',';
      } else {
        // Don't include the comma on the last one.
        line += thing;
      }
    }
    // Add an eol character or two
    line += '\r\n';
    return(line);
  };

  var sendData = function (dataToSend) {
    // AJAX stuff to actually send data. The script saves in append mode now.
    $.ajax({
  		type: 'POST',
  		cache: false,
  		url: '../Resources/SaveData.php',
      error: onSaveError,
      success: onSaveSuccess,
  		data: {
  			filename: filename,
  			filedata: dataToSend
  		}
  	});
  };

  var onSaveSuccess = function (data, textStatus, jqXHR) {
    saveSuccessCode = 0;
    numSaveAttempts++;
  };

  var onSaveError = function (data, textStatus, jqXHR) {
    console.log(textStatus);
    if (numSaveAttempts < maxSaveAttempts) {
      sendData();
      numSaveAttempts++;
      console.log(textStatus);
    } else {
      saveSuccessCode = 1;
      console.log(textStatus);
      console.log('Maximum number of save attempts exceeded.')
    }
  };

  // Initialize the data file
  sendHeader();

  // Load instruction strings
  if (gunKeyCode == 69) {
    instr1 = <?php
    $flName = "./Texts/InstructionsScreen1e-gun.txt";
    $myfile = fopen($flName, "r") or die("Unable to open file!");
    echo json_encode(fread($myfile,filesize($flName)));
    fclose($myfile);
    ?>

  } else {
    instr1 = <?php
    $flName = "./Texts/InstructionsScreen1i-gun.txt";
    $myfile = fopen($flName, "r") or die("Unable to open file!");
    echo json_encode(fread($myfile,filesize($flName)));
    fclose($myfile);
    ?>

  }

  // Make the expPrompt
  expPrompt = '<table style="width:100%">'
  + '<tr> <th>"' +
  leftKey + '" key: ' + leftObj + '</th> <th>' +
  '"' + rightKey + '" key: ' + rightObj + '</th> </tr>' + '</table>';

  // Make the instruction stimulus.
  instructStim = {
    type: "text",
    text: instr1,
    cont_key: [32]
  };

  // Make a countdown sequence to begin the task
  countdownNumbers = [
    '<div id="jspsych-countdown-numbers">3</div>',
    '<div id="jspsych-countdown-numbers">2</div>',
    '<div id="jspsych-countdown-numbers">1</div>'
  ]
  countdown = {
    type: "sequential-priming",
    stimuli: countdownNumbers,
    is_html: [true, true, true],
    choices: [],
    prompt: expPrompt,
    timing: [1000, 1000, 1000],
    response_ends_trial: false,
    feedback: false,
    timing_post_trial: 0,
    iti: 0
  };

  // Load stimulus lists

  var blueFlNames = <?php
  echo json_encode(glob("../Resources/BLUE/*.png"));
  ?>;
  var orangeFlNames = <?php
  echo json_encode(glob("../Resources/ORANGE/*.png"));
  ?>;
  // Get just the main part of the names.
  var extractName = function (str) {
    splitStr = str.split("_");
    return(splitStr[splitStr.length-1]);
  };
  var names = blueFlNames.map(extractName);

  // Choose the minority group.
  minorityGroup = rndSelect(['BLUE', 'ORANGE'], 1)[0];
  majorityGroup = minorityGroup == 'BLUE' ? 'ORANGE' : 'BLUE';

  // Choose the minority object.
  minorityObj = rndSelect(['GUN', 'NOGUN'], 1)[0];
  majorityObj = minorityObj == 'GUN' ? 'NOGUN' : 'GUN';

  // parameters
  minFaceDrawNum = Math.round(names.length * minorityProp);
  majFaceDrawNum = names.length - minFaceDrawNum;

  // Randomly select the right number of faces.
  rndNames = shuffle(names);
  minorityNames = rndNames.slice(0, minFaceDrawNum);
  majorityNames = rndNames.slice(minFaceDrawNum, names.length);

  numMinTrials = Math.round(numTrials * minorityProp);
  numMajTrials = numTrials - numMinTrials;
  minorityNames = recycle(minorityNames, numMinTrials);
  majorityNames = recycle(majorityNames, numMajTrials);

  bluePrefix = '../Resources/BLUE/B_';
  orangePrefix = '../Resources/ORANGE/O_';
  if (minorityGroup == 'BLUE') {
    blueFaces = minorityNames.map(function (fl) {
      return(bluePrefix + fl);
    });
    orangeFaces = majorityNames.map(function (fl) {
      return(orangePrefix + fl);
    });
  } else if (minorityGroup == 'ORANGE') {
    orangeFaces = minorityNames.map(function (fl) {
      return(orangePrefix + fl);
    });
    blueFaces = majorityNames.map(function (fl) {
      return(bluePrefix + fl);
    });
  }

  // Objects
  gunFls = <?php echo json_encode(glob('../Resources/GrayGuns/*.png')); ?>;
  nogunFls = <?php echo json_encode(glob('../Resources/GrayNonguns/*.png')); ?>;

  if (minorityObj == 'GUN') {
    gunFls = randomRecycle(gunFls, numMinTrials);
    nogunFls = randomRecycle(nogunFls, numMajTrials);
  } else {
    gunFls = randomRecycle(gunFls, numMajTrials);
    nogunFls = randomRecycle(nogunFls, numMinTrials);
  }

  var makeStimObjs = function (fls, condVar, condValue) {
    var tempLst = [];
    var tempObj;
    for (i=0; i<fls.length; i++) {
      fl = fls[i];
      flVec = fl.split("/");
      tempObj = {
        file: fl,
        stId: flVec[flVec.length-1]
      };
      tempObj[condVar] = condValue;
      tempLst.push(tempObj);
    }
    return(tempLst);
  };

  var bluePrimeLst = makeStimObjs(blueFaces, "prime", "blue");
  var orangePrimeLst = makeStimObjs(orangeFaces, "prime", "orange");

  var targetSet1Lst = makeStimObjs(shuffle(gunFls), "object", "gun");
  var targetSet2Lst = makeStimObjs(shuffle(nogunFls), "object", "nogun");

  // Randomize, enforcing equal contingencies
  numMinObjMinFace = Math.round(minorityProp * numMinTrials);
  numMajObjMinFace = numMinTrials - numMinObjMinFace;
  numMinObjMajFace = Math.round(minorityProp * numMajTrials);
  numMajObjMajFace = numMajTrials - numMinObjMajFace;

  makePrimeObjSets = function (primeLst, targetLst, prime, obj) {
    if (primeLst.length != targetLst.length) {
      throw "Prime and target lists must be same length";
    }
    var retVal = [];
    for (i = 0; i < primeLst.length; i++) {
      tempObj = {
        primeFl: primeLst[i],
        targetFl: targetLst[i],
        prime: prime,
        object: obj
      }
      retVal.push(tempObj);
    }
    return(retVal);
  }

  // Rename the variables so we grab the right stuff.
  if (minorityGroup === 'BLUE') {
    var minPrimeLst = bluePrimeLst;
    var majPrimeLst = orangePrimeLst;
  } else if (minorityGroup === 'ORANGE') {
    var minPrimeLst = orangePrimeLst;
    var majPrimeLst = bluePrimeLst;
  }
  if (minorityObj === 'GUN') {
    var minTargetLst = targetSet1Lst;
    var majTargetLst = targetSet2Lst;
  } else if (minorityObj === 'NOGUN') {
    var minTargetLst = targetSet2Lst;
    var majTargetLst = targetSet1Lst;
  }

  var primesMinFaceMinObj = minPrimeLst.slice(0, numMinObjMinFace);
  var targetsMinFaceMinObj = minTargetLst.slice(0, numMinObjMinFace);
  var setsMinFaceMinObj = makePrimeObjSets(
    primesMinFaceMinObj, targetsMinFaceMinObj, minorityGroup, minorityObj
  );

  var primesMinFaceMajObj = minPrimeLst.slice(numMinObjMinFace);
  var targetsMinFaceMajObj = majTargetLst.slice(0, numMajObjMinFace);
  var setsMinFaceMajObj = makePrimeObjSets(
    primesMinFaceMajObj, targetsMinFaceMajObj, minorityGroup, majorityObj
  );

  var primesMajFaceMinObj = majPrimeLst.slice(0, numMinObjMajFace);
  var targetsMajFaceMinObj = minTargetLst.slice(numMinObjMinFace);
  var setsMajFaceMinObj = makePrimeObjSets(
    primesMajFaceMinObj, targetsMajFaceMinObj, majorityGroup, minorityObj
  );

  var primesMajFaceMajObj = majPrimeLst.slice(numMinObjMajFace);
  var targetsMajFaceMajObj = majTargetLst.slice(numMajObjMinFace);
  var setsMajFaceMajObj = makePrimeObjSets(
    primesMajFaceMajObj, targetsMajFaceMajObj, majorityGroup, majorityObj
  );


  // Concatenate all those lists and randomize them.
  var fullPrimeTargetSet = setsMinFaceMinObj.concat(
    setsMinFaceMajObj, setsMajFaceMinObj, setsMajFaceMajObj
  );
  fullPrimeTargetSet = shuffle(fullPrimeTargetSet);

  // Make all the trials and timelines.
  taskTrials = {
    type: "sequential-priming",
    choices: [leftKeyCode, rightKeyCode],
    prompt: expPrompt,
    timing_stim: timing_parameters,
    is_html: [false, false, false, false],
    response_ends_trial: true,
    timeline: [],
    timing_response: timing_parameters[2] + timing_parameters[3],
    response_window: [timing_parameters[0] + timing_parameters[1], Infinity],
    feedback: true,
    key_to_advance: 32,
    //feedback_duration: 1000, // Only activate these if the check should show.
    //correct_feedback: check,
    incorrect_feedback: redX,
    timeout_feedback: tooSlow,
    timing_post_trial: 0,
    iti: 800,
    on_finish: endTrial
  };
  // Utility variables & functions for testing the study
  var numTrialTypes = [
    [0, 0],
    [0, 0]
  ];
  for (i=0; i<numTrials; i++){
    correct_answer = fullPrimeTargetSet[i].object === 'GUN' ? gunKeyCode : nogunKeyCode;
    var curPrimeFl = fullPrimeTargetSet[i].primeFl;
    var curTargetFl = fullPrimeTargetSet[i].targetFl;
    tempTrial = {
      stimuli: [
        mask,
        curPrimeFl.file,
        curTargetFl.file,
        mask
      ],
      data: {
        prime: fullPrimeTargetSet[i].prime,
        object: fullPrimeTargetSet[i].object,
        prime_id: curPrimeFl.stId,
        object_id: curTargetFl.stId,
        trial_num: i + 1
      },
      correct_choice: correct_answer
    };
    //debugger;
    taskTrials.timeline.push(tempTrial);

    // Count the trial types
    curPrime = fullPrimeTargetSet[i].prime === "BLUE" ? 0 : 1;
    curTarget = fullPrimeTargetSet[i].object === "GUN" ? 0 : 1;
    numTrialTypes[curPrime][curTarget]++;
  }

  var pidTrial = {
    type: "text",
    text: 'PID: ' + pid,
    cont_key: [32]
  };
  var thankyouTrial = {
    type: "text",
    text: 'Thank you! Please let the experimenter know you are finished.',
    cont_key: [32]
  };
  // Push everything to the big timeline in order
  timeline.push(pidTrial);
  timeline.push(instructStim);
  timeline.push(countdown);
  timeline.push(taskTrials);
  //timeline.push(saveCall);
  timeline.push(thankyouTrial);

  // try to set the background-color
  document.body.style.backgroundColor = '#d9d9d9';

  // Preload all images just to be sure.
  var gunPreloads = <?php echo json_encode(glob('../Resources/GrayGuns/*.png')); ?>;
  var nogunPreloads = <?php echo json_encode(glob('../Resources/GrayNonguns/*.png')); ?>;
  var bluePreloads = <?php echo json_encode(glob('../Resources/BLUE/*.png')); ?>;
  var orangePreloads = <?php echo json_encode(glob('../Resources/ORANGE/*.png')); ?>;
  var allPrimes = bluePreloads.concat(orangePreloads);
  var allTargets = gunPreloads.concat(nogunPreloads);

  // Preload all stimuli
  var imgNamesSizes = [];
  for (var i = 0; i < allPrimes.length; i++) {
    imgNamesSizes[i] = [allPrimes[i], primeSize];
  }
  var tempLength = imgNamesSizes.length;
  for (var i = 0; i < allTargets.length; i++) {
    imgNamesSizes[i + tempLength] = [allTargets[i], targetSize];
  }
  imgNamesSizes = imgNamesSizes.concat([ //imgNamesArr = imgNamesArr.concat([
    ['./TooSlow.png', [201, 380]],
    ['./XwithSpacebarMsg.png', [285, 380]],
    ['./MaskReal.png', targetSize],
    ['./FixationCross380x380.png', targetSize]
  ]);
  window.allWITImages = preloadResizedImages(imgNamesSizes);

  var goToSurvey = function () {
    window.location = 'https://cuboulder.qualtrics.com/jfe/form/SV_9G0W4QbdHvlZ23b?pid=' + pid;
  };

  var startExperiment = function () {
    jsPsych.init({
    	timeline: timeline,
      fullscreen: true,
      on_finish: goToSurvey
    });
  };
  startExperiment();

</script>
</html>
