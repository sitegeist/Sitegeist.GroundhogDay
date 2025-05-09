import { EventEndType, OccurenceState } from "../types";

export const getInitialEventEndType = (occurence: OccurenceState): EventEndType => {
    return (occurence.durationUnit && occurence.durationCount) ? 'duration' : 'endDate';
};
  