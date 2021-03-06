/*
 * Copyright (c) (2017) - Aikar's Minecraft Timings Parser
 *
 *  Written by Aikar <aikar@aikar.co>
 *    + Contributors (See AUTHORS)
 *
 *  http://aikar.co
 *  http://starlis.com
 *
 *  @license MIT
 *
 */

import React from "react";
import data from "../data";
import TimingRow from "./TimingRow";
import flow from "lodash/flow";
import _fp from "lodash/fp";

export default class TimingsView extends React.Component {
  static propTypes = TimingsView.props = {
    children: React.PropTypes.any,
  };

  constructor(props, ctx) {
    super(props, ctx);
    this.state = {
      limit: 40
    };
    data.provideTo(this);
  }

  render() {
    if (!this.state.timingHistoryReady) {
      return null;
    }

    let children = Object.values(data.handlerData);
    const propTotal = prop('total');
    const propCount = prop('count');

    const filter = lagFilter(propTotal, propCount, true);

    children = flow(
      _fp.filter(filter),
      _fp.sortBy(sortType)
    )(children).reverse();
    const count = children.length;

    children = children.slice(0, this.state.limit);
    return (
      <div>
        {children.map((handler) => {
          return <TimingRow timingRowDepth={0} key={handler.id} handler={handler}/>
        })}
        {count > this.state.limit ?
          <div id="show-more" onClick={() => this.setState({limit: this.state.limit + 20})}>Show More</div> : null}
      </div>
    );
  }
}
